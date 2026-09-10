<?php

namespace App\Services;

use App\Support\Hybrid;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Sinkronisasi dua arah SQLite (lokal) <-> MySQL (hosting).
 *
 * Tabel disalin dalam urutan dependensi (induk dulu -> anak).
 * Konflik diselesaikan dengan aturan "yang lebih baru menang"
 * berdasarkan kolom updated_at (fallback created_at, lalu lokal menang).
 */
class HybridSyncService
{
    /** Urutan tabel: induk -> anak (menjaga foreign key). */
    public const TABLES = ['users', 'items', 'loans', 'loan_items', 'technicians'];

    /** Kunci alami untuk deteksi duplikat lintas koneksi. */
    public const NATURAL_KEYS = [
        'users' => 'email',
        'items' => 'item_code',
        'loans' => 'uuid',
    ];

    /** @return array{ok:bool,message:string} */
    public function testConnection(?array $override = null): array
    {
        try {
            $connection = $override !== null
                ? self::connectionFor($override)
                : Hybrid::connection();

            $version = (string) $connection->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION);

            return [
                'ok' => true,
                'message' => 'Terhubung ke MySQL '.$version.' — database "'.$connection->getDatabaseName().'".',
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Gagal terhubung: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Migrasi awal: buat skema di MySQL lalu salin seluruh data lokal.
     *
     * @return array{ok:bool,message:string,summary?:array}
     */
    public function migrateToRemote(): array
    {
        if (! Hybrid::isConfigured()) {
            return ['ok' => false, 'message' => 'Konfigurasi hosting belum diisi.'];
        }

        $test = $this->testConnection();
        if (! $test['ok']) {
            return $test;
        }

        try {
            Artisan::call('migrate', [
                '--database' => Hybrid::CONNECTION,
                '--force' => true,
            ]);

            $remote = Hybrid::connection();
            $local = DB::connection();

            $remote->statement('SET FOREIGN_KEY_CHECKS=0');

            $summary = [];
            foreach (self::TABLES as $table) {
                $columns = $this->commonColumns($table);
                $remote->table($table)->delete();

                $count = 0;
                $local->table($table)
                    ->orderBy('id')
                    ->chunk(200, function ($rows) use ($remote, $table, $columns, &$count) {
                        foreach ($rows as $row) {
                            try {
                                $remote->table($table)->insert($this->castRow((array) $row, $columns));
                                $count++;
                            } catch (Throwable) {
                                $summary['skipped_'.$table] = ($summary['skipped_'.$table] ?? 0) + 1;
                            }
                        }
                    });

                $summary[$table] = $count;
            }

            $remote->statement('SET FOREIGN_KEY_CHECKS=1');

            Hybrid::markSynced(true, 'Migrasi awal ke hosting berhasil: '.json_encode($summary));

            return [
                'ok' => true,
                'message' => 'Migrasi data ke hosting berhasil.',
                'summary' => $summary,
            ];
        } catch (Throwable $e) {
            Hybrid::markSynced(false, 'Migrasi gagal: '.$e->getMessage());

            return ['ok' => false, 'message' => 'Migrasi gagal: '.$e->getMessage()];
        }
    }

    /**
     * Sinkronisasi dua arah mingguan (atau manual).
     *
     * @return array{ok:bool,message:string,summary?:array}
     */
    public function syncBidirectional(): array
    {
        if (! Hybrid::isConfigured()) {
            return ['ok' => false, 'message' => 'Konfigurasi hosting belum diisi.'];
        }

        $test = $this->testConnection();
        if (! $test['ok']) {
            Hybrid::markSynced(false, $test['message']);

            return $test;
        }

        try {
            $local = DB::connection();
            $remote = Hybrid::connection();

            $total = ['pushed' => 0, 'pulled' => 0, 'local_updated' => 0, 'remote_updated' => 0, 'conflicts' => 0];
            $perTable = [];

            foreach (self::TABLES as $table) {
                $stats = $this->syncTable($local, $remote, $table);
                $perTable[$table] = $stats;
                foreach ($total as $key => $value) {
                    $total[$key] = $value + $stats[$key];
                }
            }

            $message = 'Sinkronisasi selesai: '.$total['pushed'].' dikirim, '.$total['pulled'].' ditarik, '
                .$total['remote_updated'].' diperbarui di hosting, '.$total['local_updated'].' diperbarui lokal, '
                .$total['conflicts'].' konflik/duplikat dilewati.';

            Hybrid::markSynced(true, $message.' | '.json_encode($perTable));

            return ['ok' => true, 'message' => $message, 'summary' => $perTable];
        } catch (Throwable $e) {
            Hybrid::markSynced(false, 'Sinkronisasi gagal: '.$e->getMessage());

            return ['ok' => false, 'message' => 'Sinkronisasi gagal: '.$e->getMessage()];
        }
    }

    /**
     * Jalankan sinkronisasi bila sudah jatuh tempo (mingguan).
     *
     * @return array{ran:bool,ok?:bool,message:string}
     */
    public function runIfDue(): array
    {
        if (! Hybrid::isDue()) {
            return ['ran' => false, 'message' => 'Sinkronisasi otomatis tidak jatuh tempo / tidak aktif.'];
        }

        $result = $this->syncBidirectional();

        return ['ran' => true, 'ok' => $result['ok'], 'message' => $result['message']];
    }

    /** Sinkronkan satu tabel: push, pull, lalu selesaikan konflik newer-wins. */
    private function syncTable(
        \Illuminate\Database\Connection $local,
        \Illuminate\Database\Connection $remote,
        string $table,
    ): array {
        $columns = $this->commonColumns($table);
        $natural = self::NATURAL_KEYS[$table] ?? null;

        $localRows = $this->rowsById($local, $table);
        $remoteRows = $this->rowsById($remote, $table);

        $stats = ['pushed' => 0, 'pulled' => 0, 'local_updated' => 0, 'remote_updated' => 0, 'conflicts' => 0];

        // 1) Hanya ada di lokal -> kirim ke remote.
        foreach ($localRows as $id => $row) {
            if (isset($remoteRows[$id])) {
                continue;
            }
            if ($this->naturalKeyTaken($remote, $table, $natural, $row)) {
                $stats['conflicts']++;
                continue;
            }
            try {
                $remote->table($table)->insert($this->castRow($row, $columns));
                $remoteRows[$id] = $row;
                $stats['pushed']++;
            } catch (Throwable) {
                $stats['conflicts']++;
            }
        }

        // 2) Hanya ada di remote -> tarik ke lokal.
        foreach ($remoteRows as $id => $row) {
            if (isset($localRows[$id])) {
                continue;
            }
            if ($this->naturalKeyTaken($local, $table, $natural, $row)) {
                $stats['conflicts']++;
                continue;
            }
            try {
                $local->table($table)->insert($this->castRow($row, $columns));
                $localRows[$id] = $row;
                $stats['pulled']++;
            } catch (Throwable) {
                $stats['conflicts']++;
            }
        }

        // 3) Ada di kedua sisi -> yang lebih baru menang (updated_at).
        foreach ($localRows as $id => $localRow) {
            if (! isset($remoteRows[$id])) {
                continue;
            }
            $remoteRow = $remoteRows[$id];
            if ($this->rowsEqual($localRow, $remoteRow, $columns)) {
                continue;
            }

            $cmp = $this->compareFreshness($localRow, $remoteRow);
            $winner = $cmp >= 0 ? $localRow : $remoteRow;

            try {
                $local->table($table)->where('id', $id)
                    ->update($this->castRow($winner, $columns, true));
                $remote->table($table)->where('id', $id)
                    ->update($this->castRow($winner, $columns, true));

                if ($cmp > 0) {
                    $stats['remote_updated']++;
                } elseif ($cmp < 0) {
                    $stats['local_updated']++;
                } else {
                    $stats['remote_updated']++;
                    $stats['conflicts']++;
                }
            } catch (Throwable) {
                $stats['conflicts']++;
            }
        }

        return $stats;
    }

    private static function connectionFor(array $config): \Illuminate\Database\Connection
    {
        Hybrid::applyConnection($config);

        return DB::connection(Hybrid::CONNECTION);
    }

    /** Kolom yang ada di kedua koneksi (urutan mengikuti lokal). */
    private function commonColumns(string $table): array
    {
        $localColumns = Schema::connection(DB::connection()->getName())->getColumnListing($table);
        $remoteColumns = Schema::connection(Hybrid::CONNECTION)->getColumnListing($table);

        return array_values(array_intersect($localColumns, $remoteColumns));
    }

    /** @return array<int, array> */
    private function rowsById(\Illuminate\Database\Connection $connection, string $table): array
    {
        return $connection->table($table)
            ->orderBy('id')
            ->get()
            ->keyBy('id')
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    private function castRow(array $row, array $columns, bool $forUpdate = false): array
    {
        $payload = [];
        foreach ($columns as $column) {
            if ($forUpdate && $column === 'id') {
                continue;
            }
            $value = $row[$column] ?? null;
            if ($value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d H:i:s');
            }
            $payload[$column] = $value;
        }

        return $payload;
    }

    private function rowsEqual(array $a, array $b, array $columns): bool
    {
        foreach ($columns as $column) {
            $va = $a[$column] ?? null;
            $vb = $b[$column] ?? null;
            if ($va instanceof \DateTimeInterface) {
                $va = $va->format('Y-m-d H:i:s');
            }
            if ($vb instanceof \DateTimeInterface) {
                $vb = $vb->format('Y-m-d H:i:s');
            }
            if ((string) $va !== (string) $vb) {
                return false;
            }
        }

        return true;
    }

    /** 1 = lokal lebih baru, -1 = remote lebih baru, 0 = tak diketahui. */
    private function compareFreshness(array $localRow, array $remoteRow): int
    {
        foreach (['updated_at', 'created_at'] as $column) {
            $lt = $this->parseTime($localRow[$column] ?? null);
            $rt = $this->parseTime($remoteRow[$column] ?? null);

            if ($lt === null || $rt === null) {
                continue;
            }

            if ($lt->gt($rt)) {
                return 1;
            }
            if ($lt->lt($rt)) {
                return -1;
            }

            return 0;
        }

        return 0;
    }

    private function parseTime(mixed $value): ?\Carbon\Carbon
    {
        if ($value instanceof \DateTimeInterface) {
            return \Carbon\Carbon::instance($value);
        }
        if (is_string($value) && $value !== '') {
            try {
                return \Carbon\Carbon::parse($value);
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }

    private function naturalKeyTaken(\Illuminate\Database\Connection $connection, string $table, ?string $natural, array $row): bool
    {
        if ($natural === null || ! isset($row[$natural]) || ! isset($row['id'])) {
            return false;
        }

        return $connection->table($table)
            ->where($natural, $row[$natural])
            ->where('id', '!=', $row['id'])
            ->exists();
    }
}
