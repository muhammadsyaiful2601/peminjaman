<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BackupService;
use App\Support\Hybrid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;
use ZipArchive;

class SystemResetController extends Controller
{
    private const OPERATIONAL_TABLES = [
        'loan_items',
        'loans',
        'item_images',
        'clearance_letters',
        'students',
        'technicians',
        'items',
    ];

    private const UPLOAD_DIRECTORIES = [
        'borrow-photos',
        'return-photos',
        'items',
        'signatures',
    ];

    public function __construct(private readonly BackupService $backups)
    {
    }

    public function reset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
            'confirmation' => ['required', 'in:RESET'],
        ]);

        if (! Hash::check($validated['password'], (string) $request->user()->password)) {
            return response()->json([
                'ok' => false,
                'message' => 'Password administrator salah. Reset sistem dibatalkan.',
            ], 422);
        }

        $backupName = 'backup-sebelum-reset-'.now()->format('Y-m-d-His').'-'.bin2hex(random_bytes(3)).'.zip';
        $backupDirectory = storage_path('app/'.BackupService::SAFETY_DIR);
        $backupPath = $backupDirectory.DIRECTORY_SEPARATOR.$backupName;

        try {
            if (! is_dir($backupDirectory) && ! mkdir($backupDirectory, 0775, true) && ! is_dir($backupDirectory)) {
                throw new RuntimeException('Folder backup tidak dapat dibuat.');
            }

            $this->backups->createFullArchive($backupPath);

            if ($this->backups->isHybridAvailable()) {
                $hybrid = Hybrid::connection();
                $hybrid->getPdo();
                $dump = $this->backups->mysqlDump(Hybrid::CONNECTION);
                $zip = new ZipArchive();

                if ($zip->open($backupPath) !== true) {
                    throw new RuntimeException('Arsip backup sebelum reset tidak dapat dibuka.');
                }

                try {
                    if (! $zip->addFromString('hosting/database.sql', $dump)
                        || ! $zip->addFromString('hosting/manifest.json', (string) json_encode([
                            'database_driver' => 'mysql',
                            'database_name' => $hybrid->getDatabaseName(),
                            'created_at' => now()->toIso8601String(),
                        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))) {
                        throw new RuntimeException('Backup database hosting tidak dapat ditambahkan ke arsip.');
                    }
                } finally {
                    $zip->close();
                }
            }
        } catch (Throwable $exception) {
            if (is_file($backupPath)) {
                @unlink($backupPath);
            }

            Log::error('Backup sebelum reset sistem gagal: '.$exception->getMessage(), [
                'admin' => $request->user()->email,
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Reset dibatalkan karena backup lengkap gagal dibuat: '.$exception->getMessage(),
            ], 500);
        }

        $connections = [DB::getDefaultConnection()];
        if ($this->backups->isHybridAvailable()) {
            $connections[] = Hybrid::CONNECTION;
        }

        $deleted = [];

        try {
            foreach ($connections as $connectionName) {
                $deleted[$connectionName] = $this->clearOperationalTables($connectionName);
            }
        } catch (Throwable $exception) {
            Log::critical('Reset sistem tidak selesai pada seluruh database.', [
                'admin' => $request->user()->email,
                'backup' => $backupName,
                'completed_connections' => array_keys($deleted),
                'failed_connection' => $connectionName ?? null,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'reset_incomplete' => true,
                'backup' => $backupName,
                'message' => 'Reset tidak selesai pada seluruh database. Backup sebelum reset tersedia; pulihkan data atau ulangi pemeriksaan. Detail: '.$exception->getMessage(),
                'completed_connections' => array_keys($deleted),
            ], 500);
        }

        $warnings = [];

        foreach (self::UPLOAD_DIRECTORIES as $directory) {
            try {
                $disk = Storage::disk('public');
                if ($disk->directoryExists($directory) && ! $disk->deleteDirectory($directory)) {
                    $warnings[] = "Folder unggahan {$directory} tidak dapat dibersihkan.";
                }
            } catch (Throwable $exception) {
                Log::warning('Folder unggahan tidak dapat dibersihkan saat reset sistem.', [
                    'directory' => $directory,
                    'error' => $exception->getMessage(),
                ]);
                $warnings[] = "Folder unggahan {$directory} tidak dapat dibersihkan.";
            }
        }

        try {
            if (! Cache::flush()) {
                throw new RuntimeException('Cache store menolak permintaan pembersihan.');
            }
            $exitCode = Artisan::call('optimize:clear');
            if ($exitCode !== 0) {
                throw new RuntimeException(trim(Artisan::output()) ?: 'Perintah optimize:clear gagal.');
            }
        } catch (Throwable $exception) {
            Log::warning('Cache aplikasi tidak seluruhnya berhasil dibersihkan setelah reset.', [
                'admin' => $request->user()->email,
                'error' => $exception->getMessage(),
            ]);
            $warnings[] = 'Data sudah direset, tetapi cache aplikasi gagal dibersihkan. Jalankan pembersihan cache secara manual.';
        }

        if ($this->backups->isHybridAvailable()) {
            Hybrid::markSynced(true, 'Reset sistem berhasil pada database lokal dan hosting.');
        }

        $this->backups->markBackupCreated();

        Log::warning('Admin menjalankan reset sistem.', [
            'admin' => $request->user()->email,
            'backup' => $backupName,
            'deleted' => $deleted,
            'warnings' => $warnings,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Reset sistem berhasil. Akun admin/asisten dan pengaturan sistem tetap dipertahankan.',
            'backup' => $backupName,
            'backup_url' => '/api/backups/reset-download/'.$backupName,
            'deleted' => $deleted,
            'hybrid_reset' => count($connections) > 1,
            'warnings' => $warnings,
        ]);
    }

    public function downloadBackup(string $filename)
    {
        abort_unless(
            preg_match('/^backup-sebelum-reset-\d{4}-\d{2}-\d{2}-\d{6}-[a-f0-9]{6}\.zip$/', $filename) === 1,
            404,
        );

        $path = storage_path('app/'.BackupService::SAFETY_DIR.DIRECTORY_SEPARATOR.$filename);
        abort_unless(is_file($path), 404);

        return response()->download($path, $filename, [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /**
     * Hapus data operasional dalam urutan yang menghormati foreign key.
     *
     * @return array<string, int>
     */
    private function clearOperationalTables(string $connectionName): array
    {
        $connection = DB::connection($connectionName);
        $schema = Schema::connection($connectionName);

        return $connection->transaction(function () use ($connection, $schema): array {
            $counts = [];

            foreach (self::OPERATIONAL_TABLES as $table) {
                if (! $schema->hasTable($table)) {
                    continue;
                }

                $counts[$table] = $connection->table($table)->count();
                $connection->table($table)->delete();
            }

            return $counts;
        });
    }
}
