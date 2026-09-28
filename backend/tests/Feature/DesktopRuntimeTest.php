<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Penjaga konfigurasi runtime PHP yang dibundel aplikasi desktop.
 *
 * Ekstensi tidak bisa diaktifkan dari kode, hanya lewat berkas php.ini. Jika
 * pdo_mysql hilang dari `desktop/assets/php.ini`, seluruh mode hybrid mati
 * dengan pesan "could not find driver" - sulit dilacak karena tidak ada error
 * kompilasi, dan dump PHP untuk tes tidak memakai php.ini desktop tersebut.
 */
class DesktopRuntimeTest extends TestCase
{
    private function desktopPhpIni(): string
    {
        // __DIR__ = <repo>/backend/tests/Feature, jadi naik 3 tingkat ke root repo.
        return dirname(__DIR__, 3).'/desktop/assets/php.ini';
    }

    public function test_php_ini_desktop_mengaktifkan_pdo_mysql(): void
    {
        $path = $this->desktopPhpIni();

        $this->assertFileExists($path);

        $ini = (string) file_get_contents($path);
        $enabled = array_map(
            static fn (string $line): string => strtolower(trim($line)),
            array_filter(
                preg_split('/\R/', $ini) ?: [],
                static fn (string $line): bool => str_starts_with(trim($line), 'extension='),
            ),
        );

        $this->assertContains(
            'extension=pdo_mysql',
            $enabled,
            'Mode hybrid butuh pdo_mysql; tanpa itu koneksi MySQL hosting gagal "could not find driver".',
        );
    }

    public function test_ekstensi_wajib_lainnya_tetap_aktif_di_php_ini_desktop(): void
    {
        $ini = (string) file_get_contents($this->desktopPhpIni());

        // Ekstensi yang dibutuhkan Laravel dan fitur backup (arsip ZIP + foto).
        foreach (['curl', 'fileinfo', 'gd', 'intl', 'mbstring', 'openssl', 'pdo_sqlite', 'sqlite3', 'zip'] as $ext) {
            $this->assertStringContainsString(
                "extension={$ext}",
                $ini,
                "Ekstensi {$ext} tidak aktif di php.ini desktop.",
            );
        }
    }

    /**
     * Setelah pemulihan database, skema harus langsung dilengkapi.
     *
     * `php artisan migrate` hanya dijalankan saat aplikasi dinyalakan
     * (main.js -> boot()). Kalau pemulihan tidak memanggilnya lagi, data dari
     * backup versi lama membuat tabel/kolom baru tidak pernah ada dan
     * beberapa halaman tidak berfungsi sampai aplikasi direstart.
     */
    public function test_pemulihan_database_meminta_migrasi_lalu_restart_backend(): void
    {
        $main = (string) file_get_contents($this->desktopMain());
        $preload = (string) file_get_contents($this->desktopPreload());

        $this->assertStringContainsString(
            "ipcMain.handle('app:after-restore'",
            $main,
            'Jembatan setelah pemulihan harus tetap ada di main process.',
        );

        $handler = substr($main, (int) strpos($main, "ipcMain.handle('app:after-restore'"));
        $handler = substr($handler, 0, (int) strpos($handler, '});') ?: null);

        $this->assertStringContainsString(
            "runArtisan(['migrate', '--force'])",
            (string) $handler,
            'Setelah pemulihan, migrasi wajib dijalankan (data backup bisa versi lama).',
        );
        $this->assertStringContainsString('restartBackend()', (string) $handler);
        $this->assertStringContainsString(
            'afterRestore:',
            $preload,
            'preload harus membridge-kan afterRestore ke halaman.',
        );
    }

    private function desktopMain(): string
    {
        return dirname(__DIR__, 3).'/desktop/main.js';
    }

    private function desktopPreload(): string
    {
        return dirname(__DIR__, 3).'/desktop/preload.js';
    }
}