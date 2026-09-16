<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Hybrid;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    public function status()
    {
        $driver = (string) config('database.default');
        $hybrid = Hybrid::readConfig();
        $hybridAvailable = $hybrid !== null
            && (bool) ($hybrid['enabled'] ?? false)
            && Hybrid::isConfigured();

        return response()->json([
            'driver' => $driver,
            'sqlite' => $driver === 'sqlite' || $hybridAvailable,
            'mysql' => $driver === 'mysql' || $hybridAvailable,
            'hybrid' => $hybridAvailable,
        ]);
    }

    public function download(Request $request, string $type): StreamedResponse
    {
        abort_unless(in_array($type, ['sqlite', 'mysql'], true), 404);

        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Hash::check($validated['password'], (string) $request->user()->password)) {
            abort(422, 'Password administrator salah. Backup tidak dibuat.');
        }

        $driver = (string) config('database.default');
        $hybrid = Hybrid::readConfig();
        $hybridAvailable = $hybrid !== null
            && (bool) ($hybrid['enabled'] ?? false)
            && Hybrid::isConfigured();

        if ($type === 'sqlite') {
            abort_unless($driver === 'sqlite' || $hybridAvailable, 404, 'Backup SQLite tidak tersedia pada mode ini.');

            $databasePath = (string) config('database.connections.sqlite.database');
            abort_unless(is_file($databasePath), 404, 'File database SQLite tidak ditemukan.');

            return response()->download(
                $databasePath,
                'backup-sqlite-'.now()->format('Y-m-d-His').'.sqlite',
                ['Content-Type' => 'application/octet-stream'],
            );
        }

        abort_unless($driver === 'mysql' || $hybridAvailable, 404, 'Backup MySQL tidak tersedia pada mode ini.');

        $connection = $driver === 'mysql' ? 'mysql' : Hybrid::CONNECTION;
        $content = $this->mysqlDump($connection);

        return response()->streamDownload(
            static function () use ($content): void {
                echo $content;
            },
            'backup-mysql-'.now()->format('Y-m-d-His').'.sql',
            ['Content-Type' => 'application/sql; charset=UTF-8'],
        );
    }

    private function mysqlDump(string $connection): string
    {
        if ($connection === Hybrid::CONNECTION) {
            Hybrid::applyConnection();
        }

        $db = DB::connection($connection);
        $schema = $db->getSchemaBuilder();
        $tables = collect($schema->getTableListing());
        $output = "-- Peminjaman Barang database backup\n-- Generated: ".now()->toIso8601String()."\n\nSET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($tables as $table) {
            $quotedTable = $this->quoteIdentifier($table);
            $create = $db->selectOne("SHOW CREATE TABLE {$quotedTable}");
            $createSql = (string) ($create->{'Create Table'} ?? $create->{'Create View'} ?? '');

            if ($createSql === '') {
                continue;
            }

            $output .= "DROP TABLE IF EXISTS {$quotedTable};\n{$createSql};\n\n";
            $rows = $db->table($table)->get();

            foreach ($rows as $row) {
                $values = collect((array) $row)
                    ->map(fn ($value) => $value === null ? 'NULL' : $db->getPdo()->quote((string) $value))
                    ->implode(', ');
                $columns = collect(array_keys((array) $row))
                    ->map(fn ($column) => $this->quoteIdentifier($column))
                    ->implode(', ');
                $output .= "INSERT INTO {$quotedTable} ({$columns}) VALUES ({$values});\n";
            }

            $output .= "\n";
        }

        return $output."SET FOREIGN_KEY_CHECKS=1;\n";
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }
}
