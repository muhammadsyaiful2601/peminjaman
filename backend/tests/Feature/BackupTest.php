<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use PDO;
use Tests\TestCase;
use ZipArchive;

/**
 * Backup lengkap (database + foto) dan pemulihannya.
 *
 * Test ini memakai database SQLite sementara milik dirinya sendiri agar berkas
 * database benar-benar ada di disk (syarat backup/restore berbasis berkas), dan
 * folder foto diarahkan ke folder sementara.
 */
class BackupTest extends TestCase
{
    /** Titik waktu tetap (Senin pagi) untuk menguji siklus backup mingguan. */
    private const SENIN_PAGI = '2026-09-28 08:00:00';

    private string $databasePath;

    private string $uploadsPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = $this->temporaryPath('db', 'sqlite');
        $this->uploadsPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'peminjaman-test-uploads-'.uniqid();

        $this->createSqliteDatabase($this->databasePath, ['Budi Santoso']);
        $this->putPhoto('borrow-photos/contoh.jpg', 'foto-lama');

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->databasePath,
            'filesystems.disks.public.root' => $this->uploadsPath,
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        $this->deletePath($this->databasePath);
        $this->deletePath($this->uploadsPath);

        parent::tearDown();
    }

    public function test_backup_lengkap_memuat_database_dan_seluruh_foto(): void
    {
        Sanctum::actingAs($this->admin());

        $response = $this->postJson('/api/backups/full', ['password' => 'rahasia']);

        $response->assertOk()->assertDownload();

        $archivePath = $response->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive();

        $this->assertTrue($zip->open($archivePath) === true, 'Arsip backup tidak dapat dibuka.');
        $this->assertNotFalse($zip->locateName('database.sqlite'));
        $this->assertNotFalse($zip->locateName('uploads/borrow-photos/contoh.jpg'));

        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        $this->assertSame('sqlite', $manifest['database']);
        $this->assertSame(1, $manifest['photos']['files']);

        // Database di dalam arsip berisi data database aplikasi yang sebenarnya.
        $extracted = $this->temporaryPath('extract', 'sqlite');
        file_put_contents($extracted, (string) $zip->getFromName('database.sqlite'));
        $zip->close();

        $pdo = new PDO('sqlite:'.$extracted);
        $this->assertSame(['Budi Santoso'], $pdo->query('SELECT name FROM students')->fetchAll(PDO::FETCH_COLUMN));

        $this->deletePath($extracted);
        $this->deletePath($archivePath);
    }

    public function test_backup_sqlite_mengunduh_berkas_database_yang_valid(): void
    {
        Sanctum::actingAs($this->admin());

        $response = $this->postJson('/api/backups/sqlite', ['password' => 'rahasia']);

        $response->assertOk();
        $this->assertStringContainsString('backup-sqlite-', (string) $response->headers->get('content-disposition'));

        $snapshotPath = $response->baseResponse->getFile()->getPathname();
        $pdo = new PDO('sqlite:'.$snapshotPath);

        $this->assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM students')->fetchColumn());

        $this->deletePath($snapshotPath);
    }

    public function test_nama_berkas_backup_memuat_tanggal_dan_jam(): void
    {
        Sanctum::actingAs($this->admin());

        // Nama unduhan harus unik per backup (mengandung tanggal & jam) agar
        // arsip lama tidak tertimpa oleh backup berikutnya.
        // Format: backup-lengkap-YYYY-MM-DD-HHmmss.zip
        $cases = [
            '/api/backups/full' => '/backup-lengkap-\d{4}-\d{2}-\d{2}-\d{6}\.zip/',
            '/api/backups/sqlite' => '/backup-sqlite-\d{4}-\d{2}-\d{2}-\d{6}\.sqlite/',
        ];

        foreach ($cases as $url => $pattern) {
            $response = $this->postJson($url, ['password' => 'rahasia']);
            $response->assertOk();

            $disposition = (string) $response->headers->get('content-disposition');
            $this->assertMatchesRegularExpression($pattern, $disposition, "Nama berkas pada {$url} harus berisi tanggal & jam.");

            $file = $response->baseResponse->getFile();
            $this->deletePath($file->getPathname());
        }
    }

    public function test_password_salah_tidak_membuat_backup(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/backups/full', ['password' => 'salah'])->assertStatus(422);
    }

    public function test_pemulihan_menimpa_database_dan_foto_dari_arsip_backup(): void
    {
        Sanctum::actingAs($this->admin());

        $source = $this->temporaryPath('pulih', 'sqlite');
        $this->createSqliteDatabase($source, ['Siti Aminah', 'Andi Wijaya']);

        $archivePath = $this->temporaryPath('arsip', 'zip');
        $zip = new ZipArchive();
        $zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFile($source, 'database.sqlite');
        $zip->addFromString('uploads/borrow-photos/contoh.jpg', 'foto-baru');
        $zip->addFromString('uploads/items/baru.jpg', 'foto-baru-2');
        $zip->close();

        $response = $this->post('/api/backups/restore', [
            'password' => 'rahasia',
            'file' => new UploadedFile($archivePath, 'backup-lengkap.zip', 'application/zip', null, true),
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('photos.files', 2);

        $pdo = new PDO('sqlite:'.$this->databasePath);
        $this->assertSame(
            ['Siti Aminah', 'Andi Wijaya'],
            $pdo->query('SELECT name FROM students ORDER BY id')->fetchAll(PDO::FETCH_COLUMN),
        );

        // Foto lama ditimpa dan foto baru ikut dipulihkan.
        $this->assertSame('foto-baru', file_get_contents($this->uploadsPath.'/borrow-photos/contoh.jpg'));
        $this->assertSame('foto-baru-2', file_get_contents($this->uploadsPath.'/items/baru.jpg'));

        // Salinan pengaman data sebelum pemulihan tersedia.
        $safety = (string) $response->json('safety_backup');
        $this->assertNotSame('', $safety);
        $this->assertFileExists(storage_path('app/backups/'.$safety));

        @unlink(storage_path('app/backups/'.$safety));
        $this->deletePath($source);
        $this->deletePath($archivePath);
    }

    public function test_pemulihan_dari_berkas_sqlite_tanpa_arsip(): void
    {
        Sanctum::actingAs($this->admin());

        $source = $this->temporaryPath('langsung', 'sqlite');
        $this->createSqliteDatabase($source, ['Hendra Pratama']);

        $response = $this->post('/api/backups/restore', [
            'password' => 'rahasia',
            'file' => new UploadedFile($source, 'backup.sqlite', 'application/octet-stream', null, true),
        ]);

        // Berkas database saja tidak memuat berkas foto, dan hasil pemulihan
        // harus menyatakan itu secara eksplisit (bukan diam-diam sukses).
        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('photos.included', false)
            ->assertJsonPath('photos.files', 0);

        $pdo = new PDO('sqlite:'.$this->databasePath);
        $this->assertSame(['Hendra Pratama'], $pdo->query('SELECT name FROM students')->fetchAll(PDO::FETCH_COLUMN));

        @unlink(storage_path('app/backups/'.(string) $response->json('safety_backup')));
        $this->deletePath($source);
    }

    public function test_pemulihan_menolak_password_salah_dan_tidak_mengubah_data(): void
    {
        Sanctum::actingAs($this->admin());

        $source = $this->temporaryPath('kunci', 'sqlite');
        $this->createSqliteDatabase($source, ['Uji Coba']);

        $response = $this->post('/api/backups/restore', [
            'password' => 'salah',
            'file' => new UploadedFile($source, 'backup.sqlite', 'application/octet-stream', null, true),
        ]);

        $response->assertStatus(422);

        $pdo = new PDO('sqlite:'.$this->databasePath);
        $this->assertSame(['Budi Santoso'], $pdo->query('SELECT name FROM students')->fetchAll(PDO::FETCH_COLUMN));

        $this->deletePath($source);
    }

    public function test_pemulihan_menolak_arsip_tanpa_database(): void
    {
        Sanctum::actingAs($this->admin());

        $archivePath = $this->temporaryPath('tanpa-db', 'zip');
        $zip = new ZipArchive();
        $zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('catatan.txt', 'bukan backup');
        $zip->close();

        $this->post('/api/backups/restore', [
            'password' => 'rahasia',
            'file' => new UploadedFile($archivePath, 'bukan-backup.zip', 'application/zip', null, true),
        ])->assertStatus(422);

        $this->deletePath($archivePath);
    }

    public function test_backup_dan_pemulihan_hanya_untuk_admin(): void
    {
        Sanctum::actingAs($this->user('assistant'));

        $source = $this->temporaryPath('asisten', 'sqlite');
        $this->createSqliteDatabase($source, ['Uji Coba']);

        $this->getJson('/api/backups/status')->assertForbidden();
        $this->postJson('/api/backups/full', ['password' => 'rahasia'])->assertForbidden();
        $this->post('/api/backups/restore', [
            'password' => 'rahasia',
            'file' => new UploadedFile($source, 'backup.sqlite', 'application/octet-stream', null, true),
        ])->assertForbidden();

        $this->deletePath($source);
    }

    public function test_pemulihan_menolak_berkas_dengan_format_tidak_didukung(): void
    {
        Sanctum::actingAs($this->admin());

        $scriptPath = $this->temporaryPath('skrip', 'php');
        file_put_contents($scriptPath, '<?php echo "bukan backup";');

        $response = $this->post('/api/backups/restore', [
            'password' => 'rahasia',
            'file' => new UploadedFile($scriptPath, 'bukan-backup.php', 'text/x-php', null, true),
        ]);

        $response->assertStatus(422)->assertJsonPath('ok', false);
        $this->assertStringContainsString('Format berkas tidak didukung', (string) $response->json('message'));

        $this->deletePath($scriptPath);
    }

    public function test_berkas_dump_sql_diterima_validasi_hanya_ditolak_karena_mode_database(): void
    {
        Sanctum::actingAs($this->admin());

        $dumpPath = $this->temporaryPath('dump', 'sql');
        file_put_contents($dumpPath, "CREATE TABLE users (id INT);\n");

        // Dump .sql terdeteksi sebagai teks biasa oleh penebakan MIME, sehingga
        // pemeriksaan format harus memakai ekstensi nama berkas, bukan isi berkas.
        $response = $this->post('/api/backups/restore', [
            'password' => 'rahasia',
            'file' => new UploadedFile($dumpPath, 'backup-mysql.sql', 'text/plain', null, true),
        ]);

        $response->assertStatus(422)->assertJsonPath('ok', false);
        $this->assertStringContainsString('hanya dapat dipulihkan bila aplikasi memakai MySQL', (string) $response->json('message'));

        $this->deletePath($dumpPath);
    }

    public function test_pemulihan_melewati_entri_foto_dengan_path_berbahaya(): void
    {
        Sanctum::actingAs($this->admin());

        $source = $this->temporaryPath('aman', 'sqlite');
        $this->createSqliteDatabase($source, ['Rina-source']);

        $archivePath = $this->temporaryPath('jahat', 'zip');
        $zip = new ZipArchive();
        $zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFile($source, 'database.sqlite');
        $zip->addFromString('uploads/borrow-photos/aman.jpg', 'foto-aman');
        $zip->addFromString('uploads/../../escaped.txt', 'harus-dilewati');
        $zip->close();

        $response = $this->post('/api/backups/restore', [
            'password' => 'rahasia',
            'file' => new UploadedFile($archivePath, 'backup-lengkap.zip', 'application/zip', null, true),
        ]);

        $response->assertOk()->assertJsonPath('ok', true);

        $this->assertSame('foto-aman', file_get_contents($this->uploadsPath.'/borrow-photos/aman.jpg'));
        $this->assertFileDoesNotExist(dirname($this->uploadsPath).DIRECTORY_SEPARATOR.'escaped.txt');

        @unlink(storage_path('app/backups/'.(string) $response->json('safety_backup')));
        $this->deletePath($source);
        $this->deletePath($archivePath);
    }

    /* ------------------------------------------- regresi: foto & skema data */

    /**
     * Backup versi lama menyimpan path foto dengan prefiks `storage/`.
     * Migrasi perbaikannya tidak akan terulang saat pemulihan (tabel
     * `migrations` ikut kembali), jadi pemulihan harus merapikannya sendiri.
     */
    public function test_pemulihan_memperbaiki_path_foto_lama_dari_backup_versi_lama(): void
    {
        Sanctum::actingAs($this->admin());

        $this->putPhoto('items/lama.jpg', 'foto-lama');
        $this->putPhoto('borrow-photos/pinjam.jpg', 'foto-peminjam');
        $this->putPhoto('items/tambahan.jpg', 'foto-tambahan');

        $source = $this->temporaryPath('lama', 'sqlite');
        $this->createLegacyPhotoDatabase($source, [
            'items' => 'storage/items/lama.jpg',
            'loans' => '/storage/borrow-photos/pinjam.jpg',
            'item_images' => 'public/storage/items/tambahan.jpg',
        ]);

        $archivePath = $this->temporaryPath('lama', 'zip');
        $zip = new ZipArchive();
        $zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFile($source, 'database.sqlite');
        $zip->close();

        $response = $this->post('/api/backups/restore', [
            'password' => 'rahasia',
            'file' => new UploadedFile($archivePath, 'backup-lengkap.zip', 'application/zip', null, true),
        ]);

        // Setelah dirapikan semua path ada berkasnya -> tidak ada foto hilang.
        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('photos.missing', 0)
            ->assertJsonPath('photos.referenced', 3)
            ->assertJsonPath('warnings', []);

        $pdo = new PDO('sqlite:'.$this->databasePath);
        $this->assertSame('items/lama.jpg', $pdo->query('SELECT image FROM items')->fetchColumn());
        $this->assertSame('borrow-photos/pinjam.jpg', $pdo->query('SELECT borrow_photo FROM loans')->fetchColumn());
        $this->assertSame('items/tambahan.jpg', $pdo->query('SELECT path FROM item_images')->fetchColumn());

        @unlink(storage_path('app/backups/'.(string) $response->json('safety_backup')));
        $this->deletePath($source);
        $this->deletePath($archivePath);
    }

    /**
     * Kalau foto dirujuk database tapi berkasnya tidak ada, pengguna harus
     * diberi tahu (dulu aplikasi hanya menampilkan "berhasil dipulihkan").
     */
    public function test_pemulihan_memberi_peringatan_tentang_foto_yang_tidak_ada(): void
    {
        Sanctum::actingAs($this->admin());

        $source = $this->temporaryPath('hilang', 'sqlite');
        $this->createLegacyPhotoDatabase($source, ['items' => 'items/hilang.jpg']);

        $response = $this->post('/api/backups/restore', [
            'password' => 'rahasia',
            'file' => new UploadedFile($source, 'backup.sqlite', 'application/octet-stream', null, true),
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('photos.missing', 1)
            ->assertJsonPath('photos.sample.0', 'items/hilang.jpg');

        $warnings = implode(' ', (array) $response->json('warnings'));
        $this->assertStringContainsString('tanpa berkas foto', $warnings);
        $this->assertStringContainsString('items/hilang.jpg', $warnings);

        @unlink(storage_path('app/backups/'.(string) $response->json('safety_backup')));
        $this->deletePath($source);
    }

    /**
     * Salinan pengaman harus berisi data SEBELUM pemulihan. Dahulu salinan
     * dibuat setelah database ditimpa, sehingga isinya justru data hasil
     * pemulihan dan tidak bisa dipakai membatalkan kesalahan.
     */
    public function test_salinan_pengaman_menyimpan_data_sebelum_pemulihan(): void
    {
        Sanctum::actingAs($this->admin());

        $source = $this->temporaryPath('safety', 'sqlite');
        $this->createSqliteDatabase($source, ['Siti Aminah']);

        $response = $this->post('/api/backups/restore', [
            'password' => 'rahasia',
            'file' => new UploadedFile($source, 'backup.sqlite', 'application/octet-stream', null, true),
        ]);

        $response->assertOk()->assertJsonPath('ok', true);

        $safetyPath = storage_path('app/backups/'.(string) $response->json('safety_backup'));
        $this->assertFileExists($safetyPath);

        $pdo = new PDO('sqlite:'.$safetyPath);
        $this->assertSame(
            ['Budi Santoso'],
            $pdo->query('SELECT name FROM students')->fetchAll(PDO::FETCH_COLUMN),
            'Salinan pengaman harus memuat data sebelum pemulihan, bukan sesudahnya.',
        );

        @unlink($safetyPath);
        $this->deletePath($source);
    }

    /* ------------------------------------------------------ backup mingguan */

    public function test_pengingat_muncul_bila_belum_pernah_backup(): void
    {
        Sanctum::actingAs($this->admin());

        $this->getJson('/api/backups/status')
            ->assertOk()
            ->assertJsonPath('reminder.due', true)
            ->assertJsonPath('reminder.last_backup_at', null)
            ->assertJsonPath('reminder.interval_days', 7);
    }

    public function test_pengingat_tidak_muncul_seusai_backup_baru(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/backups/sqlite', ['password' => 'rahasia'])->assertOk();

        $this->getJson('/api/backups/status')
            ->assertOk()
            ->assertJsonPath('reminder.due', false)
            ->assertJsonPath('reminder.days_since_backup', 0);
    }

    public function test_pengingat_muncul_kembali_setelah_satu_minggu(): void
    {
        Sanctum::actingAs($this->admin());

        // Waktu awal dikunci (Senin) agar tes tidak bergantung hari saat dijalankan.
        $this->travelTo(self::SENIN_PAGI);
        $this->postJson('/api/backups/sqlite', ['password' => 'rahasia'])->assertOk();

        $this->travel(6)->days();
        $this->getJson('/api/backups/status')->assertJsonPath('reminder.due', false);

        // Senin berikutnya: tepat satu siklus berlalu, pengingat muncul lagi.
        $this->travel(1)->day();
        $this->getJson('/api/backups/status')
            ->assertJsonPath('reminder.due', true)
            ->assertJsonPath('reminder.days_since_backup', 7);
    }

    public function test_pilihan_nanti_menunda_pengingat_hingga_minggu_depan(): void
    {
        Sanctum::actingAs($this->admin());

        $this->travelTo(self::SENIN_PAGI);

        $this->postJson('/api/backups/reminder/snooze')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('reminder.due', false);

        // Masih minggu yang sama: tidak boleh muncul lagi.
        $this->travel(2)->days();
        $this->getJson('/api/backups/status')->assertJsonPath('reminder.due', false);

        // Setelah satu siklus (7 hari) muncul kembali.
        $this->travel(5)->days();
        $this->getJson('/api/backups/status')->assertJsonPath('reminder.due', true);
    }

    public function test_menunda_pengingat_hanya_untuk_admin(): void
    {
        Sanctum::actingAs($this->user('assistant'));

        $this->postJson('/api/backups/reminder/snooze')->assertForbidden();
    }

    public function test_pengingat_dan_status_backup_hanya_untuk_admin(): void
    {
        Sanctum::actingAs($this->user('assistant'));

        $this->getJson('/api/backups/status')->assertForbidden();
    }

    /* --------------------------------------------------------------- helper */

    private function admin(): User
    {
        return $this->user('admin');
    }

    private function user(string $role): User
    {
        // Cukup untuk autentikasi & pemeriksaan password: user tidak perlu
        // disimpan ke database.
        $user = new User();
        $user->forceFill([
            'name' => 'Petugas',
            'email' => $role.'@example.com',
            'role' => $role,
            'password' => Hash::make('rahasia'),
        ]);

        return $user;
    }

    private function createSqliteDatabase(string $path, array $names): void
    {
        $this->deletePath($path);

        $pdo = new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, email VARCHAR NOT NULL)');
        $pdo->exec('CREATE TABLE items (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR NOT NULL)');
        $pdo->exec('CREATE TABLE students (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR NOT NULL, email VARCHAR NOT NULL DEFAULT "")');
        // Tabel state aplikasi (dipakai pengingat backup mingguan).
        $pdo->exec('CREATE TABLE app_settings (id INTEGER PRIMARY KEY AUTOINCREMENT, key VARCHAR NOT NULL UNIQUE, value TEXT NULL, created_at DATETIME NULL, updated_at DATETIME NULL)');

        $statement = $pdo->prepare('INSERT INTO students (name) VALUES (?)');

        foreach ($names as $name) {
            $statement->execute([$name]);
        }
    }

    /**
     * Database dengan bentuk versi lama: tabel foto memakai path berprefiks
     * `storage/` persis seperti yang ditulis versi aplikasi sebelumnya.
     *
     * @param  array<string, string>  $photos  Nama tabel => path foto pada tabel itu.
     */
    private function createLegacyPhotoDatabase(string $path, array $photos): void
    {
        $this->deletePath($path);

        $pdo = new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, email VARCHAR NOT NULL)');
        $pdo->exec('CREATE TABLE students (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR NOT NULL, email VARCHAR NOT NULL DEFAULT "")');
        $pdo->exec('CREATE TABLE app_settings (id INTEGER PRIMARY KEY AUTOINCREMENT, key VARCHAR NOT NULL UNIQUE, value TEXT NULL, created_at DATETIME NULL, updated_at DATETIME NULL)');
        $pdo->exec('INSERT INTO users (email) VALUES ("admin@example.com")');

        if (isset($photos['items'])) {
            $pdo->exec('CREATE TABLE items (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR NOT NULL, image VARCHAR NULL)');
            $pdo->exec('INSERT INTO items (name, image) VALUES ("Barang uji", '.$pdo->quote($photos['items']).')');
        } else {
            $pdo->exec('CREATE TABLE items (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR NOT NULL, image VARCHAR NULL)');
        }

        if (isset($photos['loans'])) {
            $pdo->exec('CREATE TABLE loans (id INTEGER PRIMARY KEY AUTOINCREMENT, borrow_photo VARCHAR NULL, return_photo VARCHAR NULL)');
            $pdo->exec('INSERT INTO loans (borrow_photo) VALUES ('.$pdo->quote($photos['loans']).')');
        } else {
            $pdo->exec('CREATE TABLE loans (id INTEGER PRIMARY KEY AUTOINCREMENT, borrow_photo VARCHAR NULL, return_photo VARCHAR NULL)');
        }

        if (isset($photos['item_images'])) {
            $pdo->exec('CREATE TABLE item_images (id INTEGER PRIMARY KEY AUTOINCREMENT, item_id INTEGER NOT NULL, path VARCHAR NOT NULL)');
            $pdo->exec('INSERT INTO item_images (item_id, path) VALUES (1, '.$pdo->quote($photos['item_images']).')');
        } else {
            $pdo->exec('CREATE TABLE item_images (id INTEGER PRIMARY KEY AUTOINCREMENT, item_id INTEGER NOT NULL, path VARCHAR NOT NULL)');
        }
    }

    private function putPhoto(string $relative, string $contents): void
    {
        $target = $this->uploadsPath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
        @mkdir(dirname($target), 0775, true);
        file_put_contents($target, $contents);
    }

    private function temporaryPath(string $prefix, string $extension): string
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'peminjaman-test-'.$prefix.'-'.uniqid().'.'.$extension;
        $this->deletePath($path);

        return $path;
    }

    private function deletePath(string $path): void
    {
        if (is_dir($path)) {
            foreach (scandir($path) ?: [] as $entry) {
                if ($entry !== '.' && $entry !== '..') {
                    $this->deletePath($path.DIRECTORY_SEPARATOR.$entry);
                }
            }

            @rmdir($path);

            return;
        }

        if (is_file($path)) {
            @unlink($path);
        }
    }
}
