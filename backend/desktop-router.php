<?php

/*
|--------------------------------------------------------------------------
| Router PHP Built-in Server — Mode Aplikasi Desktop
|--------------------------------------------------------------------------
|
| Dipakai oleh: php -S 127.0.0.1:<port> -t public desktop-router.php
|
| Satu origin menyajikan semuanya sehingga frontend dan backend berada
| pada domain yang sama (tanpa CORS):
|   1. File statis milik Laravel di public/  -> disajikan built-in server
|      (termasuk /storage yang merupakan junction/symlink).
|   2. Aset + halaman SPA React (frontend dist) -> disajikan manual.
|   3. Sisanya (/api/*, /up, dll.) -> diteruskan ke Laravel.
|
*/

$uri = urldecode((string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'));

$publicDir = __DIR__ . DIRECTORY_SEPARATOR . 'public';
// Lokasi file unggahan: aplikasi desktop mengirim DESKTOP_UPLOAD_PATH (folder
// uploads persisten yang selamat dari update/reinstall); tanpa env, pakai
// lokasi klasik storage/app/public.
$uploadDirFromEnv = getenv('DESKTOP_UPLOAD_PATH');
$storageClassicDir = __DIR__ . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'public';
$storagePublicDir = ($uploadDirFromEnv !== false && trim($uploadDirFromEnv) !== '')
    ? rtrim(trim($uploadDirFromEnv), '/\\')
    : $storageClassicDir;
$distDir = rtrim((string) getenv('DESKTOP_FRONTEND_DIST'), DIRECTORY_SEPARATOR);

/** Pastikan path hasil realpath tetap di dalam direktori dasar (anti path-traversal). */
function desktopRouterInside(string $base, string $target): bool
{
    $baseReal = realpath($base);
    if ($baseReal === false) {
        return false;
    }
    if (! file_exists($target)) {
        return false;
    }
    $targetReal = realpath($target);

    return $targetReal !== false && str_starts_with($targetReal, $baseReal . DIRECTORY_SEPARATOR);
}

/** Kirim file statis dengan Content-Type yang sesuai. */
function desktopRouterServe(string $file): bool
{
    $map = [
        'html' => 'text/html; charset=utf-8',
        'css' => 'text/css; charset=utf-8',
        'js' => 'text/javascript; charset=utf-8',
        'mjs' => 'text/javascript; charset=utf-8',
        'json' => 'application/json',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'map' => 'application/json',
        'txt' => 'text/plain; charset=utf-8',
    ];

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $type = $map[$ext] ?? (function_exists('mime_content_type') ? (mime_content_type($file) ?: 'application/octet-stream') : 'application/octet-stream');

    header('Content-Type: ' . $type);
    header('Content-Length: ' . (string) filesize($file));
    header('Accept-Ranges: none');

    readfile($file);

    return true;
}

// 1) File statis milik Laravel (public/): storage, images, build, favicon, dll.
//    `return false` memerintahkan built-in server menyajikan file dari docroot (public/).
if ($uri !== '/' && is_file($publicDir . $uri) && desktopRouterInside($publicDir, $publicDir . $uri)) {
    return false;
}

// File upload dilayani eksplisit agar foto tetap tampil di aplikasi desktop,
// sekalipun junction/symlink `public/storage` belum terpasang. Cari di folder
// unggahan utama (env desktop) lalu di lokasi klasik sebagai cadangan.
if (str_starts_with($uri, '/storage/')) {
    $relative = ltrim(substr($uri, strlen('/storage/')), '/\\');
    foreach (array_unique([$storagePublicDir, $storageClassicDir]) as $candidateDir) {
        $storageFile = $candidateDir . DIRECTORY_SEPARATOR . $relative;
        if (is_file($storageFile) && desktopRouterInside($candidateDir, $storageFile)) {
            return desktopRouterServe($storageFile);
        }
    }
}

// 2) Aset hasil build frontend React (dist).
if ($distDir !== '' && $uri !== '/' && is_file($distDir . $uri) && desktopRouterInside($distDir, $distDir . $uri)) {
    return desktopRouterServe($distDir . $uri);
}

// 3) Fallback SPA: semua rute non-API (mis. /login, /items, /loans) memuat index.html
//    agar React Router bekerja saat aplikasi di-refresh / dibuka langsung ke halaman.
$isApiOrHealth = str_starts_with($uri, '/api') || str_starts_with($uri, '/up');
$isLaravelWebRoute = str_starts_with($uri, '/email/verify');
$hasExtension = pathinfo($uri, PATHINFO_EXTENSION) !== '';

if ($distDir !== '' && ! $isApiOrHealth && ! $isLaravelWebRoute && ! $hasExtension && is_file($distDir . DIRECTORY_SEPARATOR . 'index.html')) {
    return desktopRouterServe($distDir . DIRECTORY_SEPARATOR . 'index.html');
}

// 4) Sisanya diteruskan ke Laravel (API, /up, dan rute web).
require $publicDir . DIRECTORY_SEPARATOR . 'index.php';
