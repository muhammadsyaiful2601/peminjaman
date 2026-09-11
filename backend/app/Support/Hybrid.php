<?php

namespace App\Support;

use Illuminate\Support\Facades\Config;

/**
 * Mode Hybrid — cermin data ke database MySQL di hosting.
 *
 * Aplikasi desktop tetap memakai SQLite lokal sebagai database utama
 * (agar tetap jalan offline). Konfigurasi MySQL hosting disimpan pada
 * storage/app/hybrid.json dan dipetakan ke koneksi bernama "hybrid_mysql".
 * Sinkronisasi dua arah dijalankan berkala (default: mingguan) oleh
 * HybridSyncService (dipicu Electron / artisan hybrid:sync --due).
 */
class Hybrid
{
    public const CONNECTION = 'hybrid_mysql';

    public const INTERVAL_DAYS = 7;

    public static function configPath(): string
    {
        return storage_path('app/hybrid.json');
    }

    /**
     * @return array{enabled:bool,host:string,port:int,database:string,username:string,password:string,last_sync_at:?string,last_sync_ok:?bool,last_sync_message:?string}|null
     */
    public static function readConfig(): ?array
    {
        $path = self::configPath();
        if (! is_file($path)) {
            return null;
        }

        try {
            $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        if (! is_array($decoded)) {
            return null;
        }

        return [
            'enabled' => (bool) ($decoded['enabled'] ?? false),
            'host' => (string) ($decoded['host'] ?? ''),
            'port' => (int) ($decoded['port'] ?? 3306),
            'database' => (string) ($decoded['database'] ?? ''),
            'username' => (string) ($decoded['username'] ?? ''),
            'password' => (string) ($decoded['password'] ?? ''),
            'last_sync_at' => $decoded['last_sync_at'] ?? null,
            'last_sync_ok' => isset($decoded['last_sync_ok']) ? (bool) $decoded['last_sync_ok'] : null,
            'last_sync_message' => $decoded['last_sync_message'] ?? null,
        ];
    }

    public static function writeConfig(array $config): void
    {
        $path = self::configPath();
        @mkdir(dirname($path), 0775, true);

        file_put_contents(
            $path,
            json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );
    }

    public static function forgetConfig(): void
    {
        if (is_file(self::configPath())) {
            @unlink(self::configPath());
        }
    }

    public static function isConfigured(): bool
    {
        $config = self::readConfig();

        return $config !== null
            && $config['host'] !== ''
            && $config['database'] !== ''
            && $config['username'] !== '';
    }

    /**
     * Mendaftarkan (atau memperbarui) koneksi "hybrid_mysql" berdasarkan
     * konfigurasi tersimpan. Aman dipanggil kapan saja.
     */
    public static function applyConnection(?array $override = null): void
    {
        $config = $override ?? self::readConfig();

        if ($config === null || $config['host'] === '' || $config['database'] === '') {
            return;
        }

        Config::set('database.connections.'.self::CONNECTION, [
            'driver' => 'mysql',
            'host' => $config['host'],
            'port' => $config['port'],
            'database' => $config['database'],
            'username' => $config['username'],
            'password' => $config['password'],
            'unix_socket' => '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => 'InnoDB',
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                \PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ]);
    }

    public static function connection(): \Illuminate\Database\Connection
    {
        self::applyConnection();

        return \Illuminate\Support\Facades\DB::connection(self::CONNECTION);
    }

    /**
     * Apakah sinkronisasi mingguan perlu dijalankan sekarang?
     */
    public static function isDue(): bool
    {
        $config = self::readConfig();

        if ($config === null || ! $config['enabled'] || ! self::isConfigured()) {
            return false;
        }

        if (empty($config['last_sync_at'])) {
            return true;
        }

        try {
            $last = \Carbon\Carbon::parse($config['last_sync_at']);
        } catch (\Throwable) {
            return true;
        }

        return $last->diffInDays(\Carbon\Carbon::now()) >= self::INTERVAL_DAYS
            || $last->lt(\Carbon\Carbon::now()->subDays(self::INTERVAL_DAYS));
    }

    public static function markSynced(bool $ok, string $message): void
    {
        $config = self::readConfig() ?? [];
        $config['last_sync_at'] = \Carbon\Carbon::now()->toIso8601String();
        $config['last_sync_ok'] = $ok;
        $config['last_sync_message'] = mb_substr($message, 0, 1000);

        self::writeConfig($config);
    }
}
