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
| `app/Http/Controllers/Api/` | Controller REST API: `Auth`, `Item`, `Loan`, `Clearance`, `User`, `Technician`, `Backup`, `Branding`, `Hybrid`, `PdfFont`, `SystemReset` |
| `app/Http/Middleware/` | `CheckRole` (alias `role`) dan `CheckDesktopKey` (alias `desktop.key`, header `X-Desktop-Key`) |
| `app/Models/` | `User`, `Item`, `ItemImage`, `Loan`, `LoanItem`, `Student`, `Technician`, `AppSetting` |
| `app/Support/` | Helper `Branding`, `Hybrid`, `PublicUrl`, `QrPng`, `PdfFont` |
| `app/Services/` | `HybridSyncService` (sinkronisasi SQLite lokal ↔ MySQL hosting) |
| `app/Mail/` | Email bukti peminjaman, revisi peminjaman, dan konfirmasi pengembalian |
| `app/Console/Commands/` | `hybrid:migrate` dan `hybrid:sync` (`--due`, `--force`) |
| `app/Notifications/` | `VerifyEmailNotification` (verifikasi email akun petugas) |
| `database/seeders/` | `DatabaseSeeder` (akun awal) dan `LoanDummySeeder` (100 peminjaman contoh) |
| `resources/views/pdf/` | Template PDF: bukti QR, laporan peminjaman, surat peminjaman resmi, surat bebas labor |
| `routes/api.php` | Seluruh endpoint `/api`, termasuk route khusus desktop |

Akun awal dari seeder: `admin` / `password` (petugas utama) dan `asisten` / `password`
(asisten petugas) — login memakai **username**, bukan email.

## Font PDF (dapat dipilih pengguna)

Admin dapat mengganti font dokumen PDF pada **Pengaturan Sistem → Font PDF**.
Pilihan disimpan di `app_settings` dengan kunci `pdf_font` dan berlaku untuk
seluruh dokumen: surat bebas/tanggungan labor, surat peminjaman resmi, laporan
peminjaman, dan bukti peminjaman (QR).

Dompdf menyediakan font bawaan DejaVu serta font standar PDF Base 14
(Helvetica, Times, dan Courier). Pilihan font di aplikasi hanya menggunakan
font tersebut agar paket aplikasi tidak perlu mendistribusikan font berlisensi
pihak ketiga. Nama font sistem seperti *Calibri*, *Arial*, atau *Segoe UI* tidak
ditawarkan karena Dompdf dapat menggantinya diam-diam.

- `PdfFont::options()` menampilkan font bawaan yang dapat dirender Dompdf.
- Cache font Dompdf berada di `storage/fonts/`. Folder ini harus bisa ditulis;
  isi cache dibangkitkan ulang dan tidak disimpan di Git.
- Jika pengaturan menunjuk font yang tidak tersedia, `PdfFont::currentKey()`
  menggunakan `dejavu_sans` agar PDF tetap dapat dibuat.
- `GET /api/pdf-font` terbuka untuk semua petugas, sedangkan
  `POST /api/pdf-font` hanya untuk admin (dijaga middleware `role:admin`).

## Perintah verifikasi

```bash
php artisan test
php artisan view:cache
php artisan config:clear
```

Untuk memeriksa setiap font yang dapat dipilih benar-benar ter-embed pada
seluruh template PDF (skrip memakai SQLite in-memory, jadi tidak butuh MySQL):

```bash
php verify_pdf_font.php
```

## Lisensi

Backend ini adalah bagian dari perangkat lunak **proprietary (bukan open source)** dan
tunduk pada [`../LICENSE.md`](../LICENSE.md).

Komponen pihak ketiga tetap memakai lisensinya masing-masing — di antaranya Laravel
Framework, Laravel Sanctum, `barryvdh/laravel-dompdf`, `simplesoftwareio/simple-qrcode`
(MIT), `bacon/bacon-qr-code` (BSD-2-Clause), `dompdf/dompdf` (LGPL-2.1), dan
`smalot/pdfparser` (LGPL-3.0). Teks lisensi lengkap tersedia pada
`vendor/<vendor>/<paket>/LICENSE*` dan tidak boleh dihapus dari distribusi.
