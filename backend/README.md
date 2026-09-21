# Backend — Sistem Peminjaman Barang PNP

REST API Laravel 13 (PHP 8.3+) untuk Sistem Peminjaman Barang Jurusan Teknologi
Informasi, Politeknik Negeri Padang. Backend ini melayani dua mode deployment:

- **Website** — banyak komputer melalui jaringan/domain, database MySQL.
- **Aplikasi desktop Electron** — PHP portable + SQLite lokal, satu komputer petugas,
  tetap berjalan tanpa XAMPP/Laragon/MySQL.

> Repository ini **bukan open source**. Sistem dihibahkan untuk Politeknik Negeri
> Padang dan dilisensikan secara komersial untuk institusi lain — lihat
> [`../LICENSE.md`](../LICENSE.md).

## Menjalankan backend

```bash
composer install
cp .env.example .env          # PowerShell: Copy-Item .env.example .env
php artisan key:generate
php artisan storage:link
php artisan migrate --seed
php artisan serve --port=8000
```

Konfigurasi environment, daftar endpoint API, aturan bisnis stok/multi-item, dan
checklist deployment dijelaskan pada [`../README.md`](../README.md).

## Struktur penting

| Path | Isi |
| :--- | :--- |
| `app/Http/Controllers/Api/` | Controller REST API: `Auth`, `Item`, `Loan`, `Clearance`, `User`, `Technician`, `Backup`, `Branding`, `Hybrid` |
| `app/Http/Middleware/` | `CheckRole` (alias `role`) dan `CheckDesktopKey` (alias `desktop.key`, header `X-Desktop-Key`) |
| `app/Models/` | `User`, `Item`, `ItemImage`, `Loan`, `LoanItem`, `Technician`, `AppSetting` |
| `app/Support/` | Helper `Branding`, `Hybrid`, `PublicUrl`, `QrPng` |
| `app/Services/` | `HybridSyncService` (sinkronisasi SQLite lokal ↔ MySQL hosting) |
| `app/Mail/` | Email bukti peminjaman, revisi peminjaman, dan konfirmasi pengembalian |
| `app/Console/Commands/` | `hybrid:migrate` dan `hybrid:sync` (`--due`, `--force`) |
| `app/Notifications/` | `VerifyEmailNotification` (verifikasi email akun petugas) |
| `database/seeders/` | `DatabaseSeeder` (akun awal) dan `LoanDummySeeder` (100 peminjaman contoh) |
| `resources/views/pdf/` | Template PDF: bukti QR, laporan peminjaman, surat peminjaman resmi, surat bebas labor |
| `routes/api.php` | Seluruh endpoint `/api`, termasuk route khusus desktop |

Akun awal dari seeder: `admin` / `password` (petugas utama) dan `asisten` / `password`
(asisten petugas) — login memakai **username**, bukan email.

## Perintah verifikasi

```bash
php artisan test
php artisan view:cache
php artisan config:clear
```

## Lisensi

Backend ini adalah bagian dari perangkat lunak **proprietary (bukan open source)** dan
tunduk pada [`../LICENSE.md`](../LICENSE.md).

Komponen pihak ketiga tetap memakai lisensinya masing-masing — di antaranya Laravel
Framework, Laravel Sanctum, `barryvdh/laravel-dompdf`, `simplesoftwareio/simple-qrcode`
(MIT), `bacon/bacon-qr-code` (BSD-2-Clause), `dompdf/dompdf` (LGPL-2.1), dan
`smalot/pdfparser` (LGPL-3.0). Teks lisensi lengkap tersedia pada
`vendor/<vendor>/<paket>/LICENSE*` dan tidak boleh dihapus dari distribusi.
