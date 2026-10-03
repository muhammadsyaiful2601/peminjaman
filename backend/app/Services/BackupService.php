<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Support\Hybrid;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PDO;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Backup & pemulihan data aplikasi.
 *
 * - Backup database saja (SQLite/MySQL) untuk pemakaian cepat.
 * - Backup lengkap: satu arsip ZIP berisi database + seluruh foto unggahan
 *   (foto peminjam, foto pengembalian, gambar barang, dan aset branding)
 *   sehingga hasil pemulihan benar-benar sama seperti saat dibackup.
 */
class BackupService
{
    public const ARCHIVE_DATABASE_SQLITE = 'database.sqlite';

    public const ARCHIVE_DATABASE_SQL = 'database.sql';

    public const ARCHIVE_MANIFEST = 'manifest.json';

    public const ARCHIVE_PHOTOS_DIR = 'uploads';

    public const SAFETY_DIR = 'backups';

    /** Kunci state pengingat backup mingguan pada tabel `app_settings`. */
    public const SETTING_LAST_BACKUP = 'backup_last_at';

    public const SETTING_REMINDER_SNOOZE = 'backup_reminder_snoozed_at';

    /**
     * Jarak antar backup (hari). Pengingat muncul saat aplikasi dibuka bila
     * backup terakhir sudah lebih tua dari jarak ini, sehinggarutinitas
     * mingguan berjalan otomatis tanpa perlu mengatur ulang apa pun.
     */
    public const REMINDER_INTERVAL_DAYS = 7;

    private const SQLITE_HEADER = 'SQLite format 3';

    /** Tabel inti: berkas dianggap database aplikasi bila memuat tabel berikut. */
    private const CORE_TABLES = ['users', 'items'];

    public function driver(): string
    {
        return (string) config('database.default');
    }

    public function isHybridAvailable(): bool
    {
        $hybrid = Hybrid::readConfig();

        return $hybrid !== null && (bool) ($hybrid['enabled'] ?? false) && Hybrid::isConfigured();
    }

    public function sqlitePath(): string
    {
        return (string) config('database.connections.sqlite.database');
    }

    /** Folder akar tempat seluruh foto/gambar disimpan (disk "public"). */
    public function uploadsRoot(): string
    {
        return (string) config('filesystems.disks.public.root');
    }

    /**
     * Daftar berkas foto/gambar pada disk publik: [path relatif => path absolut].
     */
    public function photoFiles(): array
    {
        $root = $this->uploadsRoot();

        if ($root === '' || ! is_dir($root)) {
            return [];
        }

        $normalizedRoot = rtrim(str_replace('\\', '/', $root), '/');
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getFilename() === '.gitignore') {
                continue;
            }

            $absolute = str_replace('\\', '/', $file->getPathname());
            $relative = ltrim(substr($absolute, strlen($normalizedRoot)), '/');

            if ($relative !== '') {
                $files[$relative] = $file->getPathname();
            }
        }

        ksort($files);

        return $files;
    }

    public function photoStats(): array
    {
        $files = $this->photoFiles();
        $bytes = 0;

        foreach ($files as $absolute) {
            $bytes += (int) @filesize($absolute);
        }

        return ['files' => count($files), 'bytes' => $bytes];
    }

    public function manifest(): array
    {
        return [
            'application' => 'Peminjaman Barang PNP',
            'created_at' => now()->toIso8601String(),
            'timezone' => (string) config('app.timezone'),
            'database_driver' => $this->driver(),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'photos' => $this->photoStats(),
        ];
    }

    /* -------------------------------------------------------------- reminder */

    /**
     * State pengingat backup mingguan.
     *
     * Pengingat bersifat otomatis: begitu aplikasi dibuka dan backup terakhir
     * sudah lebih dari REMINDER_INTERVAL_DAYS hari (atau belum pernah sama
     * sekali), status `due` menjadi true sehingga antarmuka dapat menawarkan
     * backup. Bila belum pernah backup, hari Senin pertama aplikasi dibuka
     * menjadi awal siklus pertama.
     */
    public function reminder(): array
    {
        $now = now();
        $last = $this->lastBackupAt();
        $snoozedUntil = $this->reminderSnoozedUntil();
        $interval = self::REMINDER_INTERVAL_DAYS;

        $dueAt = $last instanceof Carbon ? $last->copy()->addDays($interval) : $now->copy();
        $isDue = $last === null || $now->greaterThanOrEqualTo($dueAt);
        $isSnoozed = $snoozedUntil instanceof Carbon && $now->lessThan($snoozedUntil);

        return [
            'due' => $isDue && ! $isSnoozed,
            'last_backup_at' => $last?->toIso8601String(),
            'days_since_backup' => $last instanceof Carbon
                ? max(0, (int) $now->diffInDays($last, absolute: true))
                : null,
            'interval_days' => $interval,
            'due_at' => $dueAt->toIso8601String(),
            'snoozed_until' => $snoozedUntil?->toIso8601String(),
        ];
    }

    /**
     * Waktu backup terakhir yang berhasil dibuat (null bila belum pernah).
     */
    public function lastBackupAt(): ?Carbon
    {
        return $this->settingDate(self::SETTING_LAST_BACKUP);
    }

    /**
     * Catat bahwa backup baru saja dibuat, sehingga pengingat mingguan diulang
     * dari titik ini (dan penundaan sebelumnya dibatalkan).
     */
    public function markBackupCreated(): void
    {
        $this->putSetting(self::SETTING_LAST_BACKUP, now()->toIso8601String());
        $this->putSetting(self::SETTING_REMINDER_SNOOZE, null);
    }

    /**
     * Tunda pengingat (dipakai saat pengguna memilih "Nanti" / backup lain kali).
     */
    public function snoozeReminder(int $days = self::REMINDER_INTERVAL_DAYS): Carbon
    {
        $until = now()->addDays(max(1, min(self::REMINDER_INTERVAL_DAYS * 8, $days)));

        $this->putSetting(self::SETTING_REMINDER_SNOOZE, $until->toIso8601String());

        return $until;
    }

    private function reminderSnoozedUntil(): ?Carbon
    {
        return $this->settingDate(self::SETTING_REMINDER_SNOOZE);
    }

    private function settingDate(string $key): ?Carbon
    {
        $value = $this->setting($key);

        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable $e) {
            return null;
        }
    }

    private function setting(string $key): ?string
    {
        try {
            return AppSetting::getValue($key);
        } catch (Throwable $e) {
            // Tabel app_settings belum tersedia pada instalasi lama: pengingat
            // diperlakukan sebagai belum pernah backup, bukan sebagai error.
            return null;
        }
    }

    private function putSetting(string $key, ?string $value): void
    {
        try {
            AppSetting::setValue($key, $value);
        } catch (Throwable $e) {
            Log::warning('State pengingat backup gagal disimpan: '.$e->getMessage());
        }
    }

    /* ---------------------------------------------------------------- backup */

    /**
     * Salinan konsisten berkas database SQLite (ditulis ke berkas sementara).
     */
    public function sqliteSnapshot(): string
    {
        $databasePath = $this->sqlitePath();

        if ($databasePath === '' || $databasePath === ':memory:' || ! is_file($databasePath)) {
            throw new RuntimeException('File database SQLite tidak ditemukan.');
        }

        $snapshotPath = tempnam(sys_get_temp_dir(), 'peminjaman-db-');

        if ($snapshotPath === false || ! copy($databasePath, $snapshotPath)) {
            throw new RuntimeException('Snapshot database SQLite gagal dibuat.');
        }

        return $snapshotPath;
    }

    /**
     * Dump MySQL portabel (tanpa membutuhkan mysqldump).
     */
    public function mysqlDump(?string $connectionName = null): string
    {
        $connection = $connectionName
            ?? ($this->driver() === 'mysql' ? 'mysql' : Hybrid::CONNECTION);

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

    /**
     * Bangun arsip backup lengkap: database + seluruh foto + manifest.
     *
     * @return array Manifest arsip yang dibuat.
     */
    public function createFullArchive(string $zipPath): array
    {
        $manifest = $this->manifest();

        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Arsip backup tidak dapat dibuat.');
        }

        $snapshot = null;

        try {
            if ($this->driver() === 'sqlite') {
                $snapshot = $this->sqliteSnapshot();
                $zip->addFile($snapshot, self::ARCHIVE_DATABASE_SQLITE);
                $manifest['database'] = 'sqlite';
            } else {
                $zip->addFromString(self::ARCHIVE_DATABASE_SQL, $this->mysqlDump());
                $manifest['database'] = 'mysql';
            }

            foreach ($this->photoFiles() as $relative => $absolute) {
                $zip->addFile($absolute, self::ARCHIVE_PHOTOS_DIR.'/'.$relative);
            }

            $zip->addFromString(
                self::ARCHIVE_MANIFEST,
                (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            );

            if ($zip->close() !== true) {
                throw new RuntimeException('Arsip backup gagal diselesaikan.');
            }
        } finally {
            if ($snapshot !== null && is_file($snapshot)) {
                @unlink($snapshot);
            }
        }

        return $manifest;
    }

    /* --------------------------------------------------------------- restore */

    /**
     * Pulihkan database + foto dari arsip backup lengkap (ZIP).
     *
     * @return array{ database: array, photos: array, safety_backup: ?string }
     */
    public function restoreArchive(string $archivePath): array
    {
        $zip = new ZipArchive();

        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException('Arsip backup tidak dapat dibuka. Pastikan berkas ZIP tidak rusak.');
        }

        try {
            return $this->restoreArchiveContents($zip);
        } finally {
            $zip->close();
        }
    }

    /**
     * Pulihkan berkas database saja (tanpa arsip): .sqlite/.db atau dump .sql.
     *
     * Berkas seperti ini TIDAK memuat foto, jadi hasil pemulihannya selalu
     * disertai audit foto (lihat describeRestorePhotos()).
     *
     * @return array{ database: array, photos: array, safety_backup: ?string }
     */
    public function restoreDatabaseOnly(string $path, string $extension): array
    {
        // Salinan pengaman harus dibuat SEBELUM database ditimpa. Dahulu
        // salinan dibuat sesudah, sehingga "salinan sebelum pemulihan"
        // berisi data hasil pemulihan dan tidak bisa membatalkan kesalahan.
        $safety = $this->safetyCopy();

        if (in_array($extension, ['sqlite', 'sqlite3', 'db'], true)) {
            if ($this->driver() !== 'sqlite') {
                throw new RuntimeException('Berkas SQLite hanya dapat dipulihkan bila aplikasi memakai SQLite.');
            }

            $database = $this->restoreSqliteFile($path);
        } else {
            if ($this->driver() !== 'mysql' && ! $this->isHybridAvailable()) {
                throw new RuntimeException('Berkas dump .sql hanya dapat dipulihkan bila aplikasi memakai MySQL.');
            }

            $database = $this->restoreSqlDump((string) file_get_contents($path));
        }

        $this->repairPhotoReferences();

        return [
            'database' => $database,
            'photos' => $this->describeRestorePhotos(false),
            'safety_backup' => $safety,
        ];
    }

    /**
     * Simpan salinan data saat ini sebelum ditimpa proses pemulihan.
     * Berkas disimpan di storage/app/backups dan hanya nama berkasnya
     * yang dikembalikan (bukan path server).
     */
    public function safetyCopy(): ?string
    {
        $directory = storage_path('app/'.self::SAFETY_DIR);

        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            Log::warning('Folder salinan pengaman backup tidak dapat dibuat.', ['directory' => $directory]);

            return null;
        }

        $stamp = now()->format('Y-m-d-His');

        try {
            if ($this->driver() === 'sqlite') {
                $target = $directory.DIRECTORY_SEPARATOR."sebelum-restore-{$stamp}.sqlite";

                if (! is_file($this->sqlitePath()) || ! copy($this->sqlitePath(), $target)) {
                    return null;
                }

                return basename($target);
            }

            $target = $directory.DIRECTORY_SEPARATOR."sebelum-restore-{$stamp}.sql";
            file_put_contents($target, $this->mysqlDump());

            return basename($target);
        } catch (Throwable $e) {
            Log::warning('Salinan pengaman sebelum restore gagal dibuat: '.$e->getMessage());

            return null;
        }
    }

    private function restoreArchiveContents(ZipArchive $zip): array
    {
        $hasSqliteArchive = $zip->locateName(self::ARCHIVE_DATABASE_SQLITE) !== false;
        $hasSqlArchive = $zip->locateName(self::ARCHIVE_DATABASE_SQL) !== false;

        if (! $hasSqliteArchive && ! $hasSqlArchive) {
            throw new RuntimeException('Arsip backup tidak memuat database (database.sqlite / database.sql).');
        }

        if ($hasSqliteArchive && $this->driver() !== 'sqlite') {
            throw new RuntimeException('Arsip berisi database SQLite, sedangkan aplikasi berjalan memakai MySQL.');
        }

        if (! $hasSqliteArchive && $this->driver() !== 'mysql') {
            throw new RuntimeException('Arsip berisi dump MySQL, sedangkan aplikasi berjalan memakai SQLite.');
        }

        $safety = $this->safetyCopy();

        // Foto diekstrak lebih dulu, baru database ditimpa. Urutan ini penting:
        // bila ekstraksi foto gagal, database lama masih utuh. Kalau dibalik,
        // database hasil pemulihan bisa menunjuk foto yang belum pernah ditulis
        // sehingga seluruh foto di halaman menjadi rusak.
        $photos = $this->extractPhotos($zip);

        if ($hasSqliteArchive) {
            $temporaryDirectory = $this->makeTemporaryDirectory();
            $temporary = $temporaryDirectory.DIRECTORY_SEPARATOR.self::ARCHIVE_DATABASE_SQLITE;

            try {
                $this->extractEntry($zip, self::ARCHIVE_DATABASE_SQLITE, $temporary);
                $database = $this->restoreSqliteFile($temporary);
            } finally {
                $this->removeDirectory($temporaryDirectory);
            }
        } else {
            $database = $this->restoreSqlDump((string) $zip->getFromName(self::ARCHIVE_DATABASE_SQL));
        }

        $this->repairPhotoReferences();

        return [
            'database' => $database,
            'photos' => $this->describeRestorePhotos(true, $photos),
            'safety_backup' => $safety,
        ];
    }

    /**
     * Pulihkan database SQLite dari sebuah berkas.
     *
     * Isi berkas disalin lewat mesin SQLite sendiri (ATTACH + salin tabel),
     * bukan dengan menimpa berkas database. Cara ini tetap aman dipakai
     * walau aplikasi sedang berjalan (worker queue memegang berkas database)
     * dan bersifat atomik karena seluruh tabel diganti dalam satu transaksi.
     */
    private function restoreSqliteFile(string $file): array
    {
        $this->validateSqliteFile($file);

        $pdo = DB::connection('sqlite')->getPdo();

        // Tahan sebentar bila proses lain sedang menulis, lalu matikan
        // pemeriksaan foreign key agar tabel bisa diganti dalam satu transaksi.
        $pdo->exec('PRAGMA busy_timeout = 15000');
        $pdo->exec('PRAGMA foreign_keys = OFF');

        $attached = str_replace(["\\", "'"], ['/', "''"], $file);
        $pdo->exec("ATTACH DATABASE '{$attached}' AS restored_backup");

        $tables = [];
        $indexes = [];
        $staged = [];

        try {
            $tables = $pdo->query("SELECT name, sql FROM restored_backup.sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'")
                ->fetchAll(PDO::FETCH_ASSOC);
            $indexes = $pdo->query("SELECT sql FROM restored_backup.sqlite_master WHERE type = 'index' AND sql IS NOT NULL")
                ->fetchAll(PDO::FETCH_COLUMN);

            // Tahap 1: salin isi backup ke tabel sementara (tabel `main` belum
            // disentuh sama sekali). Urutan ini wajib: begitu tabel dengan nama
            // sama dibuat di `main`, SQLite berhenti dapat membaca
            // `restored_backup.<nama>` dan query salin gagal dengan
            // "no such table". Backup versi lama justru punya tabel yang belum
            // ada di aplikasi sekarang (mis. `item_images`), jadi kasus ini
            // selalu terjadi saat memulihkan backup versi lama.
            foreach (array_keys($tables) as $position) {
                $stage = 'laravel_restore_stage_'.$position;

                $pdo->exec('DROP TABLE IF EXISTS temp."'.$stage.'"');
                $pdo->exec(
                    'CREATE TEMP TABLE "'.$stage.'" AS SELECT * FROM restored_backup.'
                    .$this->quoteSqliteIdentifier((string) $tables[$position]['name']),
                );
                $staged[$position] = $stage;
            }

            $pdo->beginTransaction();

            try {
                // Tahap 2: ganti tabel di `main`, lalu isi dari tabel sementara
                // (bukan dari arsip) supaya tidak ada konflik nama.
                foreach ($tables as $position => $table) {
                    $name = $this->quoteSqliteIdentifier((string) $table['name']);
                    $pdo->exec("DROP TABLE IF EXISTS {$name}");

                    if (trim((string) $table['sql']) !== '') {
                        $pdo->exec((string) $table['sql']);
                    }

                    $pdo->exec('INSERT INTO '.$name.' SELECT * FROM temp."'.$staged[$position].'"');
                }

                foreach ($indexes as $indexSql) {
                    $pdo->exec((string) $indexSql);
                }

                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                throw $e;
            }
        } catch (Throwable $e) {
            throw new RuntimeException('Database gagal dipulihkan: '.$e->getMessage());
        } finally {
            foreach ($staged as $stage) {
                $pdo->exec('DROP TABLE IF EXISTS temp."'.$stage.'"');
            }

            $pdo->exec('DETACH DATABASE restored_backup');
            $pdo->exec('PRAGMA foreign_keys = ON');
        }

        return ['engine' => 'sqlite', 'tables' => count($tables)];
    }

    /**
     * Jalankan dump SQL (MySQL) pada koneksi aktif.
     */
    private function restoreSqlDump(string $sql): array
    {
        $this->validateSqlDump($sql);

        $connection = $this->driver() === 'mysql' ? 'mysql' : Hybrid::CONNECTION;

        if ($connection === Hybrid::CONNECTION) {
            Hybrid::applyConnection();
        }

        $statements = $this->splitStatements($sql);
        $executed = 0;

        foreach ($statements as $statement) {
            DB::connection($connection)->unprepared($statement);
            $executed++;
        }

        return ['engine' => 'mysql', 'statements' => $executed];
    }

    /**
     * Ekstrak seluruh foto dari arsip lalu salin ke folder unggahan.
     * Berkas lama tetap dipertahankan; berkas dengan nama sama ditimpa.
     */
    private function extractPhotos(ZipArchive $zip): ?array
    {
        $entries = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);

            if (str_starts_with($name, self::ARCHIVE_PHOTOS_DIR.'/') && ! str_ends_with($name, '/')) {
                // Hanya path relatif yang aman (tanpa `..`, drive, atau path absolut)
                // agar arsip backup tidak dapat menulis berkas di luar folder unggahan.
                if ($this->isSafeRelativePath(substr($name, strlen(self::ARCHIVE_PHOTOS_DIR) + 1))) {
                    $entries[] = $name;
                } else {
                    Log::warning('Entri foto pada arsip backup dilewati karena path tidak aman.', ['entry' => $name]);
                }
            }
        }

        if ($entries === []) {
            return null;
        }

        $temporaryDirectory = $this->makeTemporaryDirectory();

        try {
            if ($zip->extractTo($temporaryDirectory, $entries) !== true) {
                throw new RuntimeException('Foto pada arsip backup tidak dapat diekstrak.');
            }

            $copied = $this->copyDirectory($temporaryDirectory.DIRECTORY_SEPARATOR.self::ARCHIVE_PHOTOS_DIR, $this->uploadsRoot());
        } finally {
            $this->removeDirectory($temporaryDirectory);
        }

        return ['files' => $copied];
    }

    /**
     * Ringkasan kondisi foto setelah pemulihan: berapa berkas yang ikut pulih
     * dan berapa path foto pada database yang berkasnya benar-benar tidak ada.
     *
     * @param  array{ files: int }|null  $extracted  Hasil extractPhotos() (null bila arsip tidak memuat foto).
     * @return array{ included: bool, files: int, referenced: int, missing: int, sample: array<int, string> }
     */
    private function describeRestorePhotos(bool $archive, ?array $extracted = null): array
    {
        $audit = $this->auditPhotoReferences();

        return [
            // false = berkas yang dipulihkan memang tidak membawa foto.
            'included' => $archive,
            'files' => (int) ($extracted['files'] ?? 0),
            'referenced' => $audit['referenced'],
            'missing' => $audit['missing'],
            'sample' => $audit['sample'],
        ];
    }

    /**
     * Daftar kolom yang menyimpan path foto pada database.
     *
     * Tabel/kolom diperiksa satu per satu karena hasil pemulihan bisa berasal
     * dari versi aplikasi lama (mis. tanpa tabel `item_images`).
     *
     * @return array<int, array{0: string, 1: string}>
     */
    private function photoReferenceColumns(): array
    {
        return [
            ['items', 'image'],
            ['item_images', 'path'],
            ['loans', 'borrow_photo'],
            ['loans', 'return_photo'],
        ];
    }

    /**
     * Rapikan path foto hasil pemulihan (khususnya data versi lama).
     *
     * Backup versi lama menyimpan path dengan prefiks `storage/` atau
     * `public/storage/`, sedangkan frontend kini meminta `/storage/<path>`.
     * Migrasi `fix_photo_paths_in_loans_table` pernah membetulkan kolom
     * `loans`, tetapi saat pemulihan tabel `migrations` ikut kembali ke versi
     * lama sehingga migrasi itu dianggap sudah berjalan dan TIDAK diulang —
     * foto hasil pemulihan lalu gagal dimuat. Perbaikan diulang di sini,
     * berlaku untuk semua kolom foto dan aman bila tabel/kolom belum ada.
     */
    private function repairPhotoReferences(): int
    {
        $fixed = 0;

        foreach ($this->photoReferenceColumns() as [$table, $column]) {
            if (! $this->hasPhotoColumn($table, $column)) {
                continue;
            }

            try {
                foreach ($this->photoRows($table, $column) as $key => $value) {
                    if (! is_string($value)) {
                        continue;
                    }

                    $path = $this->normalizePhotoPath($value);

                    if ($path === null || $path === $value) {
                        continue;
                    }

                    $query = DB::connection($this->restoreConnection())->table($table);

                    if (is_int($key)) {
                        $query->where('id', $key);
                    }

                    $query->update([$column => $path]);
                    $fixed++;
                }
            } catch (Throwable $e) {
                Log::warning('Path foto hasil pemulihan tidak dapat dirapikan: '.$e->getMessage(), [
                    'table' => $table,
                    'column' => $column,
                ]);
            }
        }

        if ($fixed > 0) {
            Log::info("{$fixed} path foto hasil pemulihan dirapikan ke format folder unggahan.");
        }

        return $fixed;
    }

    private function extractEntry(ZipArchive $zip, string $entry, string $target): void
    {
        $stream = $zip->getStream($entry);

        if ($stream === false) {
            throw new RuntimeException('Berkas database pada arsip backup tidak dapat dibaca.');
        }

        $output = fopen($target, 'wb');

        if ($output === false) {
            fclose($stream);

            throw new RuntimeException('Berkas sementara pemulihan tidak dapat dibuat.');
        }

        stream_copy_to_stream($stream, $output);
        fclose($stream);
        fclose($output);
    }

    /**
     * Hitung path foto pada database yang berkasnya tidak ada di folder unggahan.
     *
     * @return array{ referenced: int, missing: int, sample: array<int, string> }
     */
    private function auditPhotoReferences(): array
    {
        $paths = [];

        foreach ($this->photoReferenceColumns() as [$table, $column]) {
            if (! $this->hasPhotoColumn($table, $column)) {
                continue;
            }

            try {
                $rows = $this->photoRows($table, $column);
            } catch (Throwable $e) {
                Log::warning('Audit foto setelah pemulihan dilewati: '.$e->getMessage(), [
                    'table' => $table,
                    'column' => $column,
                ]);

                continue;
            }

            foreach ($rows as $value) {
                if (! is_string($value)) {
                    continue;
                }

                $path = $this->normalizePhotoPath($value);

                if ($path !== null) {
                    $paths[$path] = true;
                }
            }
        }

        $root = rtrim($this->uploadsRoot(), '\\/');
        $missing = [];

        foreach (array_keys($paths) as $path) {
            if (! @is_file($root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path))) {
                $missing[] = $path;
            }
        }

        return [
            'referenced' => count($paths),
            'missing' => count($missing),
            'sample' => array_slice($missing, 0, 3),
        ];
    }

    /**
     * Ambil seluruh nilai path foto pada satu kolom.
     *
     * Dikembalikan sebagai [id => path] bila kolom kunci `id` tersedia, agar
     * pembaruan menunjuk tepat satu baris tanpa membaca kolom lain.
     *
     * @return array<int|string, mixed>
     */
    private function photoRows(string $table, string $column): array
    {
        $query = DB::connection($this->restoreConnection())->table($table)->whereNotNull($column);

        return $this->hasPhotoColumn($table, 'id')
            ? $query->pluck($column, 'id')->all()
            : $query->pluck($column)->all();
    }

    /** Koneksi database yang sama dengan yang dipakai proses pemulihan. */
    private function restoreConnection(): string
    {
        return match ($this->driver()) {
            'sqlite' => 'sqlite',
            'mysql' => 'mysql',
            default => Hybrid::CONNECTION,
        };
    }

    /** True bila tabel & kolom ada pada koneksi pemulihan. */
    private function hasPhotoColumn(string $table, string $column): bool
    {
        try {
            $schema = Schema::connection($this->restoreConnection());

            return $schema->hasTable($table) && $schema->hasColumn($table, $column);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Ubah path foto apa pun menjadi path relatif di dalam folder unggahan.
     *
     * Menangani format lama (`storage/...`, `/storage/...`,
     * `public/storage/...`) maupun URL lengkap, karena frontend selalu
     * meminta `/storage/<path>`.
     */
    private function normalizePhotoPath(?string $value): ?string
    {
        $path = trim((string) $value);

        if ($path === '') {
            return null;
        }

        $path = str_replace('\\', '/', $path);
        // Buang skema + host bila path tersimpan sebagai URL lengkap.
        $path = preg_replace('#^https?://[^/]+/#i', '', $path) ?? $path;
        $path = ltrim($path, '/');
        // Buang prefiks symlink klasik yang dipakai versi lama.
        $path = preg_replace('#^(?:public/)?storage/#', '', $path) ?? $path;
        $path = trim($path, '/');

        return $path === '' ? null : $path;
    }

    /* -------------------------------------------------------------- internal */

    /**
     * Pastikan berkas benar-benar database SQLite milik aplikasi ini.
     */
    private function validateSqliteFile(string $file): void
    {
        if (! is_file($file)) {
            throw new RuntimeException('Berkas database tidak ditemukan.');
        }

        $handle = fopen($file, 'rb');
        $header = $handle === false ? '' : (string) fread($handle, 16);

        if ($handle !== false) {
            fclose($handle);
        }

        if (! str_starts_with($header, self::SQLITE_HEADER)) {
            throw new RuntimeException('Berkas yang diunggah bukan database SQLite yang valid.');
        }

        try {
            $pdo = new PDO('sqlite:'.$file, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable $e) {
            throw new RuntimeException('Database SQLite tidak dapat dibaca: '.$e->getMessage());
        }

        foreach (self::CORE_TABLES as $table) {
            if (! in_array($table, $tables, true)) {
                throw new RuntimeException("Database tidak dikenali: tabel \"{$table}\" tidak ditemukan.");
            }
        }
    }

    private function validateSqlDump(string $sql): void
    {
        if (trim($sql) === '') {
            throw new RuntimeException('Berkas dump MySQL kosong.');
        }

        if (! str_contains(strtolower($sql), 'create table')) {
            throw new RuntimeException('Berkas dump MySQL tidak dikenali (tidak memuat CREATE TABLE).');
        }
    }

    /**
     * Pisahkan dump SQL menjadi pernyataan utuh tanpa memotong isi teks.
     * Tanda `;` di dalam kutipan atau komentar tidak dianggap akhir pernyataan
     * sehingga nilai teks yang memuat `;` tetap utuh saat dipulihkan.
     *
     * @return array<int, string>
     */
    private function splitStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $length = strlen($sql);
        $inQuote = false;
        $quoteChar = "'";

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];

            if ($inQuote) {
                $buffer .= $char;

                if ($char === '\\' && $i + 1 < $length) {
                    $buffer .= $sql[++$i];

                    continue;
                }

                if ($char === $quoteChar) {
                    if ($i + 1 < $length && $sql[$i + 1] === $quoteChar) {
                        $buffer .= $sql[++$i];

                        continue;
                    }

                    $inQuote = false;
                }

                continue;
            }

            if ($char === "'" || $char === '"' || $char === '`') {
                $inQuote = true;
                $quoteChar = $char;
                $buffer .= $char;

                continue;
            }

            // Komentar baris (--) dan blok (/* */) dilewati.
            if ($char === '-' && ($sql[$i + 1] ?? '') === '-') {
                while ($i < $length && $sql[$i] !== "\n") {
                    $i++;
                }

                continue;
            }

            if ($char === '/' && ($sql[$i + 1] ?? '') === '*') {
                $end = strpos($sql, '*/', $i + 2);
                $i = $end === false ? $length : $end + 1;

                continue;
            }

            if ($char === ';') {
                $statement = trim($buffer);

                if ($statement !== '') {
                    $statements[] = $statement;
                }

                $buffer = '';

                continue;
            }

            $buffer .= $char;
        }

        $statement = trim($buffer);

        if ($statement !== '') {
            $statements[] = $statement;
        }

        return $statements;
    }

    /**
     * Salin isi folder secara rekursif (berkas lama dengan nama sama ditimpa).
     */
    private function copyDirectory(string $from, string $to): int
    {
        if (! is_dir($from)) {
            return 0;
        }

        if (! is_dir($to) && ! @mkdir($to, 0775, true) && ! is_dir($to)) {
            throw new RuntimeException('Folder tujuan foto tidak dapat dibuat.');
        }

        $copied = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($from, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            $target = rtrim($to, '\\/').DIRECTORY_SEPARATOR.$iterator->getSubPathname();

            if ($item->isDir()) {
                if (! is_dir($target)) {
                    @mkdir($target, 0775, true);
                }

                continue;
            }

            if (@copy($item->getPathname(), $target)) {
                $copied++;
            }
        }

        return $copied;
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($directory);
    }

    /**
     * Path relatif aman untuk ditulis ke dalam folder unggahan:
     * tidak boleh kosong, absolut, memuat drive, atau naik ke folder atas.
     */
    private function isSafeRelativePath(string $path): bool
    {
        if ($path === '' || str_contains($path, "\0")) {
            return false;
        }

        $normalized = str_replace('\\', '/', $path);

        if (str_starts_with($normalized, '/') || preg_match('/^[A-Za-z]:/', $normalized) === 1) {
            return false;
        }

        foreach (explode('/', $normalized) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }

        return true;
    }

    private function makeTemporaryDirectory(): string
    {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'peminjaman-restore-'.uniqid();

        if (! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Folder sementara pemulihan tidak dapat dibuat.');
        }

        return $directory;
    }

    private function quoteSqliteIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }
}
