<?php

namespace App\Support;

/**
 * URL publik untuk tautan yang harus dapat dibuka dari perangkat lain.
 *
 * Aplikasi desktop berjalan lokal (127.0.0.1), sehingga tautan berbasis
 * APP_URL tidak bisa dibuka dari luar. Saat komputer petugas terhubung ke
 * internet, Electron membuka tunnel (cloudflared) dan menuliskan URL publik
 * ke env PUBLIC_APP_URL maupun file storage/app/desktop-public-url.txt.
 * URL tautan unduh bukti peminjaman diambil dari sumber tersebut.
 */
class PublicUrl
{
    /**
     * URL publik saat ini, atau null bila tunnel tidak aktif.
     */
    public static function get(): ?string
    {
        $fromEnv = trim((string) config('app.public_url', ''));
        if ($fromEnv !== '' && preg_match('#^https?://#i', $fromEnv)) {
            return rtrim($fromEnv, '/');
        }

        $fromFile = self::fromFile();
        if ($fromFile !== null) {
            return $fromFile;
        }

        return null;
    }

    /**
     * Baca URL publik yang dituliskan aplikasi desktop (fallback dinamis,
     * karena env proses PHP tidak berubah saat tunnel menyala di tengah sesi).
     */
    public static function fromFile(): ?string
    {
        $path = storage_path('app/desktop-public-url.txt');
        if (! is_file($path)) {
            return null;
        }

        $url = trim((string) @file_get_contents($path));
        if ($url === '' || ! preg_match('#^https?://[^\s]+$#i', $url)) {
            return null;
        }

        return rtrim($url, '/');
    }
}
