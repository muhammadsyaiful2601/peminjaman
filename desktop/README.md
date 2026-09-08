# Aplikasi Desktop — Peminjaman Barang PNP

Pembungkus Electron untuk sistem peminjaman barang Politeknik Negeri Padang.
Menghasilkan installer `.exe` Windows yang mandiri: PHP portable dan Laravel dibundel,
sedangkan database SQLite dibuat di profil pengguna — **tanpa XAMPP/Laragon/MySQL**.

```
┌─────────────────────────────────────────────────┐
│  Installer .exe (NSIS, logo PNP)                │
│  ┌───────────────────────────────────────────┐  │
│  │ Jendela Electron (SPA React hasil build)  │  │
│  └───────────────────┬───────────────────────┘  │
│                      │ http://127.0.0.1:<port>  │
│  ┌───────────────────▼───────────────────────┐  │
│  │ PHP portable: php -S + desktop-router.php │  │
│  │  • API Laravel + SQLite (%APPDATA%)       │  │
│  │  • /up health, queue:work, /storage       │  │
│  └───────────────────────────────────────────┘  │
└─────────────────────────────────────────────────┘
```

## Struktur

| File | Fungsi |
| :--- | :--- |
| `main.js` | Main process: salin runtime backend ke AppData, injeksi konfigurasi SQLite/SMTP, migrasi + seed, spawn PHP server & queue worker, health check `/up`, splash + wizard + jendela utama, menu, IPC wizard |
| `preload.js` | Bridge aman `window.desktop` (contextIsolation + sandbox) |
| `splash.html` | Layar pembuka saat menyiapkan backend |
| `setup.html` / `setup.js` | Wizard konfigurasi email (SMTP/Brevo) + tombol kirim email tes + Lewati |
| `backend/desktop-router.php` | Router PHP built-in: file statis Laravel + SPA React + API Laravel dalam satu origin |
| `scripts/prepare-php.ps1` | Unduh PHP 8.4 portable ke `resources/php` |
| `scripts/prepare-build.mjs` | Rakit `resources/` (backend template, frontend dist) |
| `scripts/generate-icons.mjs` | Logo PNP SVG → `build/icon.png` + `build/icon.ico` |
| `scripts/smoke-test.mjs` | Uji pipeline tanpa Electron (migrasi, seed, server, health, API) |

## Menjalankan di mode pengembangan

Butuh `php` di PATH dan frontend hasil build:

```powershell
# dari root repo
frontend:  cd frontend && npm install && npm run build
desktop:   cd desktop && npm install
desktop:   npm start
```

Mode dev memakai PHP sistem + folder `backend/` repo langsung, dan database SQLite
tersimpan di `%APPDATA%/peminjaman-pnp-desktop/peminjaman.sqlite` (produk name di package.json).
`.env` asli backend **tidak disentuh** — override dikirim lewat environment variable.

## Membuat installer (.exe)

```powershell
cd desktop

# 1. Unduh PHP portable (sekali saja)
powershell -ExecutionPolicy Bypass -File scripts\prepare-php.ps1

# 2. Generate ikon dari logo kampus
npm run icons

# 3. Rakit resources + build installer ke desktop/release/
npm run dist
```

Hasil: `desktop/release/Peminjaman Barang PNP Setup 1.0.1.exe`

Perilaku instalasi/first-run di komputer pengguna:

1. Installer memasang aplikasi (shortcut desktop + start menu, ikon logo PNP).
2. Saat pertama dibuka: splash "Menyiapkan aplikasi…" (backend disalin ke
   `%APPDATA%`, SQLite dibuat, migrasi + seed berjalan otomatis).
3. **Wizard konfigurasi email** muncul — isi SMTP, tombol *Kirim Email Tes*,
   atau *Lewati*. Setelah masuk halaman login desktop, pengaturan juga dapat
   dibuka melalui tombol **Pengaturan Email**. Bisa dibuka ulang lewat menu
   **Aplikasi → Pengaturan Email…** (`Ctrl+E`). Perubahan SMTP langsung memuat
   ulang backend dan queue worker.
4. Aplikasi utama terbuka. Login pertama: `admin` / `password` (dari seeder).
5. Email bukti peminjaman/pengembalian terkirim hanya saat komputer online;
   saat offline alur peminjaman tetap aman (sudah di-wrap try/catch).

## Data, backup, dan update

- Database utama: `%APPDATA%\Peminjaman Barang PNP\peminjaman.sqlite`.
- Konfigurasi lokal dan `APP_KEY`: `%APPDATA%\Peminjaman Barang PNP\desktop-config.json`.
- Backup dilakukan saat aplikasi tertutup dengan menyalin folder data tersebut.
- Resource dapat dipasang di drive `C:`, `D:`, atau `E:`; resource mengikuti lokasi installer.
- Template backend akan disalin ulang otomatis ketika `TEMPLATE_VERSION` berubah.

## Keamanan

- Server PHP hanya listen di `127.0.0.1` (tidak terlihat dari jaringan).
- Kredensial SMTP disimpan pada konfigurasi lokal profil pengguna Windows dan diinjeksi hanya ke proses backend.
- Renderer sandboxed tanpa akses Node; navigasi eksternal dibuka di browser.

## Catatan

- `db:seed` membuat akun `admin`/`password` — segera ganti password setelah login pertama.
- Port backend mulai dari `8642`, otomatis bergeser bila terpakai.
- Update template backend: naikkan `TEMPLATE_VERSION` di `main.js` agar AppData
  disalin ulang (kredensial mail & APP_KEY dipertahankan).
