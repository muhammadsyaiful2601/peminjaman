<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    /** Format berkas yang boleh diunggah untuk pemulihan data. */
    private const RESTORE_EXTENSIONS = ['zip', 'sqlite', 'sqlite3', 'db', 'sql'];

    public function __construct(private readonly BackupService $backups)
    {
    }

    public function status()
    {
        $driver = (string) config('database.default');
        $hybridAvailable = $this->backups->isHybridAvailable();
        $reminder = $this->backups->reminder();

        return response()->json([
            'driver' => $driver,
            'sqlite' => $driver === 'sqlite' || $hybridAvailable,
            'mysql' => $driver === 'mysql' || $hybridAvailable,
            'hybrid' => $hybridAvailable,
            // Backup lengkap (database + seluruh foto) selalu tersedia.
            'full' => true,
            'photos' => $this->backups->photoStats(),
            // Pengingat backup mingguan (lihat service BackupService::reminder()).
            'reminder' => $reminder,
            'last_backup_at' => $reminder['last_backup_at'],
        ]);
    }

    /**
     * Tunda pengingat backup mingguan (tombol "Nanti" pada dialog pengingat).
     * Default: satu siklus mingguan (7 hari).
     */
    public function snoozeReminder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'days' => ['nullable', 'integer', 'min:1', 'max:60'],
        ]);

        $days = (int) ($validated['days'] ?? BackupService::REMINDER_INTERVAL_DAYS);
        $until = $this->backups->snoozeReminder($days);

        return response()->json([
            'ok' => true,
            'message' => 'Pengingat backup ditunda sampai '.$until->translatedFormat('l, j F Y').'.',
            'reminder' => $this->backups->reminder(),
        ]);
    }


    /**
     * Unduh backup: `sqlite`/`mysql` (database saja) atau `full`
     * (arsip ZIP berisi database + seluruh foto).
     */
    public function download(Request $request, string $type): HttpResponse
    {
        abort_unless(in_array($type, ['sqlite', 'mysql', 'full'], true), 404);

        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Hash::check($validated['password'], (string) $request->user()->password)) {
            abort(422, 'Password administrator salah. Backup tidak dibuat.');
        }

        $driver = (string) config('database.default');
        $hybridAvailable = $this->backups->isHybridAvailable();

        if ($type === 'full') {
            $archivePath = tempnam(sys_get_temp_dir(), 'peminjaman-backup-').'.zip';

            try {
                $this->backups->createFullArchive($archivePath);
            } catch (\Throwable $e) {
                Log::error('Backup lengkap gagal dibuat: '.$e->getMessage());
                @unlink($archivePath);

                abort(500, $e->getMessage());
            }

            // Arsip siap: pengingat mingguan dihitung ulang dari sekarang.
            $this->backups->markBackupCreated();

            return response()->download(
                $archivePath,
                'backup-lengkap-'.now()->format('Y-m-d-His').'.zip',
                ['Content-Type' => 'application/zip'],
            )->deleteFileAfterSend(true);
        }

        if ($type === 'sqlite') {
            abort_unless($driver === 'sqlite' || $hybridAvailable, 404, 'Backup SQLite tidak tersedia pada mode ini.');

            try {
                $snapshotPath = $this->backups->sqliteSnapshot();
            } catch (\Throwable $e) {
                Log::error('SQLite backup snapshot failed: '.$e->getMessage());

                abort(500, $e->getMessage());
            }

            $this->backups->markBackupCreated();

            return response()->download(
                $snapshotPath,
                'backup-sqlite-'.now()->format('Y-m-d-His').'.sqlite',
                ['Content-Type' => 'application/octet-stream'],
            )->deleteFileAfterSend(true);
        }

        abort_unless($driver === 'mysql' || $hybridAvailable, 404, 'Backup MySQL tidak tersedia pada mode ini.');

        $content = $this->backups->mysqlDump();
        $this->backups->markBackupCreated();

        return response()->streamDownload(
            static function () use ($content): void {
                echo $content;
            },
            'backup-mysql-'.now()->format('Y-m-d-His').'.sql',
            ['Content-Type' => 'application/sql; charset=UTF-8'],
        );
    }

    /**
     * Pulihkan database (dan foto bila arsip backup lengkap) dari berkas yang
     * diunggah admin. Sebelum menimpa, sistem menyimpan salinan data saat ini
     * di `storage/app/backups` agar masih dapat dikembalikan secara manual.
     */
    public function restore(Request $request): JsonResponse
    {
        // Allowlist dicek dari ekstensi nama berkas, bukan dari tebakan MIME
        // isi berkas: dump .sql terdeteksi sebagai teks biasa sehingga aturan
        // `mimes` akan menolaknya berulang kali.
        $validated = $request->validate([
            'password' => ['required', 'string'],
            'file' => ['required', 'file', 'max:1048576'],
        ]);

        $file = $request->file('file');
        $extension = strtolower((string) $file->getClientOriginalExtension());

        if (! in_array($extension, self::RESTORE_EXTENSIONS, true)) {
            return response()->json([
                'ok' => false,
                'message' => 'Format berkas tidak didukung. Gunakan arsip .zip hasil Backup Lengkap, atau berkas .sqlite/.db/.sql.',
            ], 422);
        }

        if (! Hash::check($validated['password'], (string) $request->user()->password)) {
            return response()->json([
                'ok' => false,
                'message' => 'Password administrator salah. Pemulihan database dibatalkan.',
            ], 422);
        }

        try {
            $result = $extension === 'zip'
                ? $this->backups->restoreArchive((string) $file->getRealPath())
                : $this->backups->restoreDatabaseOnly((string) $file->getRealPath(), $extension);
        } catch (\Throwable $e) {
            Log::warning('Pemulihan database gagal: '.$e->getMessage());

            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $photos = $result['photos']['files'] ?? null;
        $message = 'Database berhasil dipulihkan.';

        if ($photos !== null) {
            $message .= " {$photos} berkas foto ikut dipulihkan.";
        }

        if ($result['safety_backup'] !== null) {
            $message .= " Salinan data sebelum pemulihan disimpan sebagai {$result['safety_backup']}.";
        }

        if ($this->backups->isHybridAvailable()) {
            $message .= ' Mode hybrid aktif: jalankan "Sinkron Sekarang" agar data hosting menyesuaikan.';
        }

        Log::info('Database dipulihkan dari backup oleh '.$request->user()->email.'.', [
            'database' => $result['database'],
            'photos' => $photos,
            'safety_backup' => $result['safety_backup'],
        ]);

        return response()->json([
            'ok' => true,
            'message' => $message,
            'database' => $result['database'],
            'photos' => $result['photos'],
            'safety_backup' => $result['safety_backup'],
        ]);
    }
}
