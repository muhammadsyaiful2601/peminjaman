# Sistem Peminjaman Barang — Jurusan Teknologi Informasi, Politeknik Negeri Padang

Aplikasi untuk **inventaris**, **peminjaman multi-barang**, **pengembalian dengan verifikasi foto & kondisi**,
**peminjaman resmi (surat)**, **laporan resmi**, dan **bukti transaksi berbasis QR Code + email**.
Mahasiswa/peminjam **tidak membuat akun** — petugas memasukkan data peminjam dan menyerahkan barang melalui aplikasi.

> Versi installer desktop saat ini: **1.0.2** (lihat `desktop/release/`).
> Build installer terbaru sudah memuat Cloudflare Tunnel + perbaikan email bukti.

## Pilih Mode Deployment

Repository ini menyediakan dua mode penggunaan:

| Mode | Cocok untuk | Database | Server eksternal | Unduh bukti dari email |
| :--- | :--- | :--- | :--- | :--- |
| Website | Dipakai banyak komputer melalui jaringan/domain | MySQL production | PHP web server + database | Tautan publik selalu (domain hosting) |
| Desktop Windows | Dipakai offline pada satu komputer petugas | SQLite lokal | Tidak perlu Laragon/XAMPP/MySQL | PDF lampiran selalu; tautan publik otomatis saat komputer online (Cloudflare Quick Tunnel, tanpa VPS/hosting) |

Website dan desktop memakai source frontend, API, migration, seeder, dan aturan bisnis yang sama. Perbedaannya hanya pada cara menjalankan backend dan database.

## Daftar Isi Deployment

- [Prasyarat](#persyaratan)
- [Menjalankan Lokal](#menjalankan-di-lokal)
- [Deploy Website Production](#deploy-website-production)

> **Catatan versi:** panduan ini mencakup seluruh fitur sampai build desktop 1.0.2 —
> email bukti offline-proof (QR inline + lampiran PDF) dan tombol unduh publik otomatis
> via Cloudflare Quick Tunnel saat komputer petugas online (tanpa VPS/hosting).
- [Build dan Instalasi Desktop](#build-dan-instalasi-desktop-windows)
- [Publikasi ke GitHub](#publikasi-ke-github)
- [Keamanan Secret](#keamanan-secret)
- [Checklist Setelah Deploy](#checklist-setelah-deploy)

## Ringkasan Sistem

- Frontend React SPA untuk dashboard petugas.
- Backend Laravel REST API (Laravel 13, PHP 8.3+) dengan autentikasi Sanctum.
- Satu transaksi dapat berisi beberapa jenis barang dengan jumlah unit berbeda.
- Stok dikurangi ketika transaksi dibuat, bukan ketika disetujui; dicek & dikunci atomik (gagal total bila satu barang kurang).
- Bukti peminjaman dikirim via email berisi **QR Code inline (PNG)**, **kode peminjaman fallback**, dan **lampiran PDF bukti**.
- Bila komputer petugas online, email juga berisi **tombol unduh PDF via URL publik sementara (Cloudflare Quick Tunnel)** — tanpa VPS/hosting.
- PDF berisi data peminjam, foto verifikasi, seluruh barang, QR Code, logo kampus, dan logo SI.
- Unduh bukti publik via UUID (tanpa login; UUID bertindak sebagai token keamanan).
- Laporan peminjaman dapat difilter (status, tanggal, teknisi), dicetak resmi, dan diunduh sebagai PDF.
- Peminjaman resmi mendukung peminjaman skala besar dengan surat PDF dan banyak barang.
- Waktu aplikasi menggunakan WIB (`Asia/Jakarta`).
- Versi desktop **1.0.3+** dapat memperbarui otomatis dari GitHub Releases di latar belakang (popup restart bila siap).

## Fitur

### Inventaris

Petugas dapat melihat daftar barang, mencari berdasarkan nama/kode/kategori, dan melihat stok dengan pagination. Admin dan asisten dapat menambah, mengubah, dan menghapus barang. Setiap barang memiliki kode unik, nama, kategori, stok, dan gambar opsional.

### Peminjaman Multi-Item

Saat membuat peminjaman, petugas dapat menambahkan beberapa barang. Setiap baris memiliki `item_id` dan `qty`. Barang yang sama tidak boleh muncul dua kali dalam satu transaksi.

Data peminjam:

- Nama dan email wajib.
- Nomor telepon dan NIM/NIP opsional.
- Foto peminjam wajib diambil melalui kamera browser.

Pembuatan transaksi berjalan dalam database transaction. Semua stok dikunci dan dicek terlebih dahulu; jika salah satu barang tidak cukup, seluruh transaksi ditolak. Jika berhasil, status langsung menjadi `borrowed`, stok berkurang, email bukti dikirim (QR inline + lampiran PDF + tombol unduh bila online), dan `borrowed_at` diisi.

### Pengembalian

Petugas mencari transaksi melalui scan QR (kamera), UUID, kode peminjaman, atau upload PDF bukti.
Pengembalian hanya dapat dilakukan untuk transaksi berstatus `borrowed`.

Petugas wajib memilih kondisi `bagus`, `rusak`, atau `hilang`. Catatan kondisi opsional. Frontend meminta foto bukti pengembalian, kemudian sistem mengubah status menjadi `returned`, mengembalikan stok seluruh item transaksi, dan mengirim email konfirmasi pengembalian.

### Email Bukti (Offline-Proof) + Unduh Publik saat Online

Setiap peminjaman baru mengirim email ke peminjam berisi:

1. **QR Code inline (PNG)** — tampil langsung di badan email (kompatibel Gmail/Outlook).
2. **Kode peminjaman** (`PJM-YYYY-XXXX`) sebagai fallback bila QR tak terbaca.
3. **Lampiran PDF bukti peminjaman** — berisi QR, foto verifikasi, dan detail barang; dapat dibuka
   di perangkat mana pun tanpa perlu terhubung ke aplikasi.
4. **Tombol unduh PDF kondisional** — hanya muncul bila aplikasi mendeteksi URL publik aktif
   (mode desktop online via tunnel, atau mode website dengan `PUBLIC_APP_URL`/domain publik).
   Tautan menunjuk ke unduhan publik tanpa login (`GET /api/loans/qr/{uuid}/download`).

> Tombol unduh hanya valid selama komputer petugas online & aplikasi terbuka; bila tidak dapat
> dibuka, gunakan lampiran PDF pada email yang sama.

### Mode Hybrid — Cermin Data ke MySQL Hosting

Aplikasi desktop dapat dijalankan dalam **mode hybrid**: SQLite lokal tetap menjadi database
utama (agar aplikasi tetap jalan offline), sementara salinannya dicerminkan ke database
**MySQL di hosting** dan disinkronkan dua arah secara berkala.

Pengaturan berada di **tombol gear ⚙ di sudut kiri bawah** aplikasi desktop (khusus desktop),
yang membuka popup "Hosting & Sinkronisasi" berisi:

- Form koneksi hosting: **Host/Link, Port, Nama Database, Username, Password** MySQL.
- **Simpan & Tes Koneksi** — menyimpan konfigurasi lalu menguji koneksi ke MySQL hosting.
- **Migrasi Data ke Hosting** — membuat skema di MySQL (menjalankan migrasi Laravel pada
  koneksi hosting) lalu menyalin seluruh data lokal sebagai sinkronisasi awal.
- **Sinkron Sekarang** — sinkronisasi dua arah manual kapan saja.
- **Aktifkan/Matikan Otomatis** — mengaktifkan sinkronisasi otomatis **setiap minggu** (7 hari);
  aplikasi desktop memicu pemeriksaan berkala (tiap 6 jam) dan backend menentukan sendiri
  apakah sudah jatuh tempo, sehingga aman bila komputer sempat mati.

Aturan sinkronisasi (`php artisan hybrid:sync`):

1. Tabel diproses sesuai urutan dependensi: `users` → `items` → `loans` → `loan_items` → `technicians`.
2. Baris yang hanya ada di satu sisi disalin ke sisi lainnya.
3. Baris yang ada di kedua sisi dibandingkan lewat kolom `updated_at` — **yang lebih baru menang**;
   bila waktu tidak tersedia, lokal dianggap sumber utama.
4. Duplikat kunci alami (`email`, `item_code`, `uuid`) dari sisi berbeda id dicatat sebagai konflik
   dan dilewati agar tidak merusak relasi.

Endpoint pendukung (dilindungi header `X-Desktop-Key`, tidak publik):
`GET /api/hybrid/status`, `POST /api/hybrid/config`, `POST /api/hybrid/test`,
`POST /api/hybrid/migrate`, `POST /api/hybrid/sync`, `POST /api/hybrid/sync-due`,
`POST /api/hybrid/toggle`. Konfigurasi tersimpan di `storage/app/hybrid.json` (desktop)
dan runtime PHP bawaan sudah mengaktifkan ekstensi `pdo_mysql`.

> Tombol gear hanya muncul pada aplikasi desktop versi **1.0.4** ke atas.

> Catatan: foto peminjaman/barang tetap tersimpan sebagai file di komputer lokal; yang
> tersinkron adalah data (termasuk path fotonya). Untuk backup file, gunakan folder
> `storage/app/public` pada rutinitas backup komputer.

### Akun dan Hak Akses

| Fitur | Admin | Asisten |
| :--- | :---: | :---: |
| Melihat barang | Ya | Ya |
| Mengelola barang | Ya | Ya |
| Melihat peminjaman | Ya | Ya |
| Membuat peminjaman | Ya | Ya |
| Memproses pengembalian | Ya | Ya |
| Scan/lookup QR, kode, dan PDF | Ya | Ya |
| Mengelola akun user | Ya | Tidak |
| Mengubah profil sendiri | Ya | Ya |

Token akun disimpan di browser dan dikirim sebagai `Authorization: Bearer`. Sesi otomatis berakhir setelah 30 menit tanpa aktivitas, termasuk setelah browser dibuka kembali. Token Sanctum juga dikonfigurasi berlaku selama 30 menit. Logout manual atau respons API `401` akan menghapus sesi lokal.

### Laporan Peminjaman

Halaman `/reports` menyediakan laporan transaksi dengan filter status, teknisi (sebagai penanggungjawab),
dan rentang tanggal. Laporan menampilkan ringkasan jumlah transaksi serta tabel detail peminjaman. Nama penandatangan dan NIP dapat diisi, tersimpan otomatis di browser, dan dicantumkan pada dokumen.

Laporan dapat:

- Dicetak langsung sebagai dokumen resmi dengan kop Politeknik Negeri Padang, Jurusan Teknologi Informasi, dan Program Studi Sistem Informasi.
- Diunduh sebagai PDF melalui backend Dompdf.
- Memuat periode laporan, tanggal cetak, tabel bergaris, nama penandatangan, dan NIP.

Saat mencetak langsung dari browser, nonaktifkan opsi **Headers and footers** pada dialog print agar URL dan metadata browser tidak ikut tercetak.

### Peminjaman Resmi Skala Besar

Halaman `/loans/official` digunakan petugas untuk membuat peminjaman resmi yang terdiri dari banyak jenis barang dan jumlah unit. Form menyediakan data peminjam, NIM mahasiswa opsional, tujuan kegiatan, periode peminjaman, serta nama dan NIP penandatangan. Setelah dikirim, sistem memeriksa stok secara atomik, membuat transaksi peminjaman, mengurangi stok, dan mengunduh surat resmi dalam format PDF.

Data transaksi peminjam tersimpan pada daftar peminjaman. Setelah surat dibuat, tombol **Proses Barang Kembali**
membuka detail transaksi. Petugas dapat mengambil foto bukti, memilih kondisi barang (`bagus`/`rusak`/`hilang`),
dan sistem mengirim email konfirmasi pengembalian ke peminjam.

## Alur Operasional

1. Petugas login.
2. Petugas memilih satu atau lebih barang dan memasukkan jumlah masing-masing.
3. Petugas memasukkan data mahasiswa.
4. Petugas mengambil foto peminjam melalui kamera.
5. Sistem mengecek stok semua barang dalam satu transaksi.
6. Sistem mengurangi stok, membuat transaksi berstatus `borrowed`, dan mengirim email QR.
7. Mahasiswa menyimpan lampiran PDF bukti / QR / kode peminjaman dari email.
8. Saat kembali, petugas mencari transaksi dan memeriksa barang.
9. Petugas memasukkan kondisi serta foto bukti pengembalian.
10. Sistem mengembalikan stok, mengubah status menjadi `returned`, dan mengirim konfirmasi email.

Tidak ada tahap `approve` atau `reject` pada implementasi saat ini. Route untuk kedua aksi tersebut juga tidak tersedia.

## Teknologi

| Bagian | Teknologi |
| :--- | :--- |
| Frontend | React 19, Vite, Tailwind CSS v4, Axios, Lucide React |
| Kamera/QR | HTML5 camera API, `html5-qrcode`, `qrcode.react` |
| Backend | Laravel 13, PHP 8.3+, Laravel Sanctum |
| Email/PDF | Laravel Mail, Simple QR Code, Dompdf |
| Database | MySQL atau SQLite |
| Waktu | PHP/Laravel `Asia/Jakarta` (WIB) |

## Struktur Direktori

```text
backend/
    app/Http/Controllers/Api/    Controller API
    app/Http/Middleware/          Middleware role
    app/Mail/                     Email bukti peminjaman (QR inline + lampiran PDF + tombol unduh kondisional) & konfirmasi pengembalian
   app/Support/                  Helper QR PNG (GD) & URL publik tunnel
    app/Models/                   User, Item, Loan, LoanItem
    config/                       Konfigurasi aplikasi dan Sanctum
    database/migrations/           Struktur tabel
    database/seeders/              Seeder akun dan data dummy
    public/images/                 Logo PDF bukti transaksi
    resources/views/emails/        Template email
    resources/views/pdf/            Template PDF bukti dan laporan
    routes/api.php                 Route REST API

frontend/
    src/api/                       Axios client dan bearer token
    src/components/                Layout, kamera, dan komponen UI
    src/context/                   AuthContext
    src/hooks/                     Idle session hook
    src/pages/                     Dashboard, barang, peminjaman, peminjaman resmi, laporan, scan QR (kamera/manual, multi-metode),
                                    detail transaksi, pengembalian, user, teknisi, profil, lupa/reset password
```

## Persyaratan

- PHP 8.3 atau lebih baru.
- Composer 2.x.
- Node.js 20 atau lebih baru.
- MySQL 8+ untuk deployment, atau SQLite untuk pengembangan sederhana.
- Browser dengan akses kamera untuk foto peminjam dan scan QR.
- Kamera hanya dapat digunakan melalui `localhost` atau HTTPS.

## Menjalankan di Lokal

### 1. Backend

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan storage:link
php artisan migrate --seed
php artisan serve --port=8000
```

Di Windows PowerShell, gunakan pengganti `cp` berikut:

```powershell
Copy-Item .env.example .env
```

Untuk menghapus dan membuat ulang seluruh database lokal:

```bash
php artisan migrate:fresh --seed
```

Untuk menambahkan atau memperbarui 100 peminjaman dummy tanpa mengubah seeder utama:

```bash
php artisan db:seed --class=LoanDummySeeder
```

Seeder dummy menggunakan prefix `DUMMY-PJM-`, sehingga aman dijalankan ulang dan hanya mengganti data dummy yang dibuatnya.

### 2. Frontend

```bash
cd frontend
npm install
npm run dev
```

URL pengembangan:

- Frontend: `http://localhost:5173`
- Backend: `http://localhost:8000`

Vite meneruskan `/api` dan `/storage` ke backend lokal. Dalam deployment production, frontend dan backend sebaiknya berada di domain yang sama atau menggunakan reverse proxy agar path `/api` dan `/storage` tetap tersedia.

## Konfigurasi Environment

Salin `backend/.env.example` menjadi `backend/.env`, lalu sesuaikan minimal:

```dotenv
APP_NAME="Politeknik Negeri Padang"
APP_URL=https://domain-anda.example
APP_ENV=production
APP_DEBUG=false

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=peminjaman_barang
DB_USERNAME=...
DB_PASSWORD=...

FILESYSTEM_DISK=public
QUEUE_CONNECTION=database
```

Untuk email production, gunakan SMTP yang valid. Jangan memasukkan password SMTP, `APP_KEY`, atau secret lain ke Git.

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=admin@example.com
MAIL_FROM_NAME="Politeknik Negeri Padang"
```

Pada konfigurasi contoh, mailer dapat menggunakan `log`; email hanya ditulis ke log Laravel dan tidak dikirim ke penerima.

### SQLite untuk Pengembangan

Jika tidak ingin menjalankan MySQL saat pengembangan website, ubah `backend/.env` menjadi:

```dotenv
DB_CONNECTION=sqlite
DB_DATABASE=C:/path/ke/repository/backend/database/database.sqlite
```

Buat file database lalu jalankan migration:

```powershell
New-Item -ItemType File backend/database/database.sqlite -Force
cd backend
php artisan migrate --seed
```

Untuk website production multi-pengguna, gunakan MySQL atau MariaDB. SQLite desktop hanya ditujukan untuk data lokal satu komputer.

## Akun Seed Default

Seeder membuat akun berikut jika belum ada:

| Role | Email | Password awal |
| :--- | :--- | :--- |
| Admin | `admin@kampus.ac.id` | `password` |
| Asisten | `asisten@kampus.ac.id` | `password` |

Segera ganti password default setelah instalasi dan jangan memakai password tersebut di production.

## API

Semua endpoint berada di bawah prefix `/api`. Kecuali login dan download PDF QR, endpoint membutuhkan bearer token Sanctum. Endpoint pengelolaan dibatasi oleh role.

### Autentikasi

| Method | Endpoint | Akses | Keterangan |
| :--- | :--- | :--- | :--- |
| POST | `/api/login` | Publik | Menghasilkan token dan data user |
| POST | `/api/logout` | Auth | Menghapus token aktif |
| GET | `/api/user` | Auth | Mengambil user aktif |

### Barang

| Method | Endpoint | Akses | Keterangan |
| :--- | :--- | :--- | :--- |
| GET | `/api/items` | Auth | Daftar, search, filter kategori, pagination |
| GET | `/api/items/{item}` | Auth | Detail barang |
| POST | `/api/items` | Admin/Asisten | Tambah barang dan gambar opsional |
| PUT | `/api/items/{item}` | Admin/Asisten | Ubah barang |
| DELETE | `/api/items/{item}` | Admin/Asisten | Hapus barang |

### Peminjaman

| Method | Endpoint | Akses | Keterangan |
| :--- | :--- | :--- | :--- |
| GET | `/api/loans` | Auth | Daftar, filter status, search, pagination |
| GET | `/api/loans/{loan}` | Auth | Detail transaksi |
| GET | `/api/loans/qr/{uuid}` | Auth | Lookup melalui UUID QR |
| GET | `/api/loans/report/download` | Auth | Mengunduh laporan peminjaman dalam format PDF; mendukung filter status/tanggal dan data penandatangan |
| POST | `/api/loans/official/download` | Admin/Asisten | Membuat peminjaman skala besar dan mengunduh surat resmi PDF |
| GET | `/api/loans/code/{code}` | Admin/Asisten | Lookup melalui kode peminjaman |
| POST | `/api/loans` | Admin/Asisten | Membuat transaksi dan mengurangi stok |
| POST | `/api/loans/{loan}/return` | Admin/Asisten | Memproses pengembalian |
| POST | `/api/loans/upload-pdf` | Admin/Asisten | Membaca UUID/kode dari PDF |
- `POST /api/desktop/mail-test` (khusus desktop, header `X-Desktop-Key`): mengirim email percobaan dari wizard.
| GET | `/api/loans/qr/{uuid}/download` | Publik | Mengunduh PDF; UUID berfungsi sebagai token akses |

### User

| Method | Endpoint | Akses |
| :--- | :--- | :--- |
| GET | `/api/users` | Admin |
| POST | `/api/users` | Admin |
| PUT | `/api/users/{user}` | Admin |
| DELETE | `/api/users/{user}` | Admin |

## Contoh Payload Peminjaman

`POST /api/loans` menggunakan `multipart/form-data` karena menyertakan foto. Format multi-item:

```text
items[0][item_id]=1
items[0][qty]=2
items[1][item_id]=4
items[1][qty]=1
borrower_name=Nama Mahasiswa
borrower_email=mahasiswa@example.com
borrower_phone=08123456789
borrower_student_id=123456
borrow_photo=<file gambar>
```

Field wajib: `items`, setiap `item_id`, setiap `qty`, `borrower_name`, `borrower_email`, dan `borrow_photo`. Backend masih menerima format lama `item_id` dan `qty` untuk kompatibilitas client lama.

## Data dan Aturan Bisnis

- `loans` menyimpan identitas peminjam dan metadata transaksi.
- `loan_items` menyimpan setiap barang dan jumlah unit dalam transaksi.
- `item_id` dan `qty` pada `loans` dipertahankan sebagai fallback untuk data transaksi lama.
- Status yang digunakan: `pending`, `borrowed`, `returned`, dan `rejected`; transaksi baru dari UI langsung `borrowed`.
- Stok dikurangi saat peminjaman dibuat.
- Stok dikembalikan saat transaksi `borrowed` diproses sebagai `returned`.
- Kondisi pengembalian saat ini disimpan satu kali pada level transaksi, bukan per item.
- Foto peminjam dan foto pengembalian disimpan pada disk `public`.
- Penghapusan barang mengikuti aturan foreign key database yang berlaku pada migration.

## Email, PDF, dan QR

Email peminjaman berisi daftar semua barang, jumlah, kode, dan link download PDF. Email pengembalian berisi daftar barang yang dikembalikan dan kondisi transaksi.

Isi PDF meliputi kode transaksi, identitas peminjam, foto verifikasi, tabel semua barang, status, QR Code, dan waktu WIB. QR Code berisi UUID transaksi. Logo PDF berada di:

- `backend/public/images/logo_kampus.png`
- `backend/public/images/si.png`

Download PDF menggunakan URL publik berikut:

```text
GET /api/loans/qr/{uuid}/download
```

UUID harus diperlakukan sebagai rahasia karena siapa pun yang memiliki UUID dapat mengunduh PDF transaksi tersebut.

## Perintah Verifikasi

Backend:

```bash
cd backend
php artisan test
php artisan view:cache
php artisan config:clear
```

Frontend:

```bash
cd frontend
npm run build
npm run lint
```

Test otomatis yang tersedia saat ini masih berupa test contoh Laravel. Belum tersedia test integrasi khusus untuk stok, multi-item, autentikasi, role, email, PDF, dan pengembalian.

## Deploy Website Production

Gunakan website untuk banyak komputer melalui domain atau jaringan. Server membutuhkan PHP 8.3+, extension Laravel, Composer 2, Node.js 20+, dan MySQL/MariaDB.

```bash
git clone https://github.com/USERNAME/REPOSITORY.git /var/www/peminjaman
cd /var/www/peminjaman/backend
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate --force
cd ../frontend
npm ci
npm run build
```

Document root web server harus menunjuk ke `backend/public`. Sajikan isi `frontend/dist` pada domain yang sama atau proxy `/api` dan `/storage` ke Laravel. Aktifkan HTTPS agar kamera dan verifikasi email bekerja.

### Environment production

Buat database MySQL dengan collation `utf8mb4`, lalu isi `backend/.env`:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://peminjaman.example.ac.id
FRONTEND_URL=https://peminjaman.example.ac.id
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=peminjaman
DB_USERNAME=peminjaman_app
DB_PASSWORD=PASSWORD_KUAT
FILESYSTEM_DISK=public
QUEUE_CONNECTION=database
CACHE_STORE=file
SESSION_DRIVER=file
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=no-reply@example.com
MAIL_FROM_NAME="Peminjaman Barang PNP"
```

`MAIL_FROM_ADDRESS` wajib valid. Setelah `.env` siap:

```bash
cd /var/www/peminjaman/backend
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan optimize
sudo chown -R www-data:www-data storage bootstrap/cache public/storage
sudo chmod -R ug+rwx storage bootstrap/cache
php artisan queue:work --sleep=3 --tries=3 --timeout=120
```

Jalankan `queue:work` sebagai service Supervisor/systemd pada production. Setelah update, jalankan `php artisan queue:restart`.

### Update website

```bash
cd /var/www/peminjaman
git pull origin main
cd backend
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize
php artisan queue:restart
cd ../frontend
npm ci
npm run build
```

## Build dan Instalasi Desktop Windows

Desktop membundel PHP portable, Laravel, frontend, dan SQLite. Komputer pengguna tidak membutuhkan Laragon, XAMPP, MySQL, Composer, atau Node.js. Database dibuat otomatis di `%APPDATA%`.

```powershell
Push-Location frontend
npm install
npm run build
Pop-Location
Push-Location desktop
npm install
powershell -ExecutionPolicy Bypass -File scripts\prepare-php.ps1
npm run dist
Pop-Location
```

Uji pipeline sebelum dibagikan:

```powershell
node desktop/scripts/smoke-test.mjs
```

Instalasi komputer kampus:

1. Jalankan `Peminjaman Barang PNP Setup 1.0.2.exe`.
2. Pilih lokasi instalasi, termasuk drive `C:` atau `E:`.
3. Buka aplikasi dan tunggu migration SQLite serta seed selesai.
4. Isi SMTP melalui wizard atau **Aplikasi → Pengaturan Email**.
5. Kirim email tes dan simpan konfigurasi.
6. Login awal dengan `admin` / `password`, lalu ganti password.

Resource mengikuti lokasi instalasi. Data dan database tersimpan di `%APPDATA%\Peminjaman Barang PNP`. Untuk backup, tutup aplikasi lalu salin folder tersebut; database utama adalah `peminjaman.sqlite`.

## Tutorial Mandiri untuk Komputer Lain

Bagian ini ditujukan untuk pengguna kampus yang tidak ingin membuka terminal atau menulis kode.

### Cara paling mudah memasang aplikasi desktop

1. Buka halaman **Releases** pada repository GitHub aplikasi.
2. Pilih release terbaru.
3. Unduh file installer Windows dengan ekstensi `.exe`.
4. Setelah unduhan selesai, buka file installer tersebut.
5. Jika Windows menampilkan peringatan keamanan, pilih informasi selengkapnya lalu tetap jalankan hanya jika file berasal dari repository resmi kampus.
6. Ikuti langkah instalasi sampai selesai.
7. Pilih lokasi pemasangan. Drive `C:` maupun `E:` dapat digunakan.
8. Buka aplikasi melalui shortcut Desktop atau Start Menu.
9. Tunggu proses persiapan database selesai pada pembukaan pertama.
10. Isi pengaturan SMTP melalui jendela konfigurasi email.
11. Gunakan tombol **Kirim Email Tes** untuk memastikan email berjalan.
12. Simpan pengaturan email.
13. Masuk menggunakan akun awal yang diberikan administrator.
14. Segera ubah password akun setelah berhasil masuk.

Komputer pengguna tidak perlu memasang Laragon, XAMPP, MySQL, PHP, Composer, Node.js, atau aplikasi tambahan lain. Aplikasi desktop sudah membawa backend PHP dan menggunakan database SQLite lokal.

### Cara mendapatkan source melalui GitHub Desktop

Langkah ini hanya diperlukan oleh pengembang atau administrator yang ingin mengambil source project, bukan oleh pengguna biasa aplikasi desktop.

1. Pasang **GitHub Desktop** dari situs resmi GitHub.
2. Masuk menggunakan akun GitHub yang memiliki akses ke repository.
3. Pilih **Clone a repository**.
4. Pilih repository `peminjaman` dari daftar repository GitHub.
5. Tentukan folder penyimpanan project.
6. Tekan **Clone** dan tunggu sampai selesai.
7. Untuk mengambil perubahan terbaru, buka project di GitHub Desktop lalu tekan **Fetch origin** dan **Pull origin**.

Source hasil clone belum menjadi aplikasi siap pakai. Untuk penggunaan biasa, unduh installer dari halaman **Releases**. Source clone hanya diperlukan untuk pengembangan atau pembuatan installer baru.

### Cara menyiapkan website dari hasil clone

Deployment website tidak dapat dilakukan hanya dengan membuka folder hasil clone. Website membutuhkan server hosting, PHP, database MySQL, domain, dan pengaturan email.

Administrator website perlu melakukan hal berikut melalui panel hosting atau meminta bantuan penyedia server:

1. Hubungkan hosting dengan repository GitHub.
2. Pilih branch utama sebagai sumber deployment.
3. Atur folder website ke folder `backend/public`.
4. Buat database MySQL dan pengguna database.
5. Isi pengaturan aplikasi, alamat website, database, dan SMTP pada halaman environment hosting.
6. Jalankan migration dan seeder melalui fitur deployment hosting.
7. Aktifkan penyimpanan file publik untuk gambar dan PDF.
8. Aktifkan worker queue agar email peminjaman dan pengembalian dikirim.
9. Aktifkan HTTPS.
10. Uji login, input barang, peminjaman, pengembalian, upload foto, PDF, QR, dan email.

Jika hosting tidak menyediakan pengaturan PHP, database, dan queue, gunakan installer desktop untuk komputer petugas atau minta administrator server menyiapkan website menggunakan bagian **Deploy Website Production**.

### Pengaturan email pada komputer desktop

Pengaturan email hanya perlu dilakukan satu kali pada setiap komputer desktop.

1. Buka aplikasi.
2. Pada halaman login, tekan **Pengaturan Email**, atau gunakan menu **Aplikasi → Pengaturan Email**.
3. Masukkan alamat server SMTP dari penyedia email.
4. Masukkan port SMTP.
5. Masukkan username SMTP.
6. Masukkan password atau SMTP key.
7. Masukkan email pengirim yang sudah terdaftar pada penyedia SMTP.
8. Masukkan alamat email tujuan untuk pengujian.
9. Tekan **Kirim Email Tes**.
10. Periksa inbox dan folder spam.
11. Jika berhasil, tekan **Simpan & Mulai Aplikasi**.

Email membutuhkan koneksi internet. Fitur inventaris dan transaksi tetap menggunakan database lokal, tetapi email tidak dapat dikirim ketika komputer offline.

### Backup data desktop

1. Tutup aplikasi.
2. Buka File Explorer.
3. Masukkan `%APPDATA%` pada baris alamat.
4. Cari folder `Peminjaman Barang PNP`.
5. Salin folder tersebut ke flash drive, hard disk eksternal, atau penyimpanan aman.

Untuk memulihkan data, tutup aplikasi pada komputer tujuan lalu ganti folder data aplikasinya dengan salinan backup. Jangan mengedit file SQLite secara manual.

## Publikasi ke GitHub

Pastikan `.env`, password SMTP, `APP_KEY`, database SQLite, `vendor`, dan `node_modules` tidak ikut commit.

```powershell
git status --short
git diff -- . ':!desktop/release'
```

Untuk repository baru:

```powershell
git init
git add README.md .gitignore backend frontend desktop start-dev.bat
git commit -m "Initial release aplikasi peminjaman"
git branch -M main
git remote add origin https://github.com/muhammadsyaiful2601/peminjaman.git
git push -u origin main
```

Ganti URL remote dengan repository Anda. Gunakan Personal Access Token atau Git Credential Manager, bukan password GitHub biasa.

Installer `.exe` dibagikan melalui **GitHub Release** (bukan di-commit ke source). Setelah
`npm run dist`, buat tag seperti `v1.0.4`, buat Release, lalu upload **tiga file** dari
`desktop/release/` sebagai Release Asset: installer `.exe`, `latest.yml`, dan `.exe.blockmap`
(yang dua terakhir dibutuhkan untuk fitur auto-update — lihat [Pembaruan Otomatis](#aplikasi-desktop-electron)).

## Keamanan Secret

- Rotasi credential SMTP yang pernah dibagikan atau masuk history Git.
- Gunakan `APP_DEBUG=false` dan HTTPS pada website production.
- Document root web server harus `backend/public`.

## Checklist Deployment

1. Siapkan PHP 8.3+, Composer, Node.js, dan database production.
2. Deploy source `backend` dan `frontend`; jangan deploy `.env` dari komputer lokal.
3. Buat `.env` production, isi `APP_KEY`, database, URL, filesystem, dan SMTP.
4. Set `APP_ENV=production` dan `APP_DEBUG=false`.
5. Jalankan `composer install --no-dev --optimize-autoloader` di backend.
6. Jalankan `php artisan migrate --force`.
7. Jalankan `php artisan storage:link` dan pastikan `storage` dapat ditulis oleh PHP.
8. Jalankan `npm install` dan `npm run build` di frontend.
9. Sajikan hasil frontend melalui web server dan arahkan `/api` serta `/storage` ke backend.
10. Jalankan `php artisan config:cache` dan `php artisan route:cache` setelah konfigurasi production siap.
11. Uji login, pembuatan multi-item, email, download PDF, scan/lookup QR, dan pengembalian.
12. Ganti password akun seed dan periksa permission file upload.

Tidak ada Dockerfile, konfigurasi Nginx/Apache, atau pipeline CI/CD di repository ini. Konfigurasi web server dan proses build production perlu disiapkan sesuai provider hosting yang digunakan.

## Aplikasi Desktop (Electron)

Aplikasi ini juga tersedia sebagai **aplikasi desktop Windows offline** di folder `desktop/`:
PHP runtime + SQLite dibundel, jadi tidak perlu XAMPP/Laragon atau server. Fitur email
tetap dapat dipakai saat komputer terhubung internet (dikonfigurasi lewat wizard saat
pertama kali dibuka, dapat dibuka ulang via menu **Aplikasi → Pengaturan Email**).

### Unduh bukti publik saat komputer petugas online (tanpa VPS/hosting)

Sejak build terbaru, aplikasi desktop otomatis membuka **Cloudflare Quick Tunnel**
(`cloudflared`, gratis, tanpa akun) setiap kali komputer petugas terhubung internet:

1. Tunnel memberi URL publik sementara, mis. `https://xxxx.trycloudflare.com`.
2. URL tersebut ditulis ke `storage/app/desktop-public-url.txt` dan env `PUBLIC_APP_URL`.
3. Email bukti memakai URL itu untuk tombol **Unduh Bukti Peminjaman (PDF)**
   (`GET /api/loans/qr/{uuid}/download` — publik, tanpa login; UUID sebagai token).
4. Bila komputer offline, tombol tidak disertakan — lampiran PDF pada email yang sama
   tetap menjadi bukti yang sah.

Catatan:

- Tautan tunnel hanya valid selama aplikasi terbuka & komputer online
  (URL berubah tiap aplikasi di-restart).
- Endpoint sensitif desktop (`POST /api/desktop/mail-test`) dilindungi header
  `X-Desktop-Key` agar tidak disalahgunakan saat server terekspos via tunnel.
- Konfigurasi terkait: `PUBLIC_APP_URL` dan `DESKTOP_API_KEY` (dibangkitkan otomatis
  oleh aplikasi desktop dan disimpan di `desktop-config.json`).
- File pendukung: `backend/app/Support/PublicUrl.php`, `backend/app/Support/QrPng.php`,
  `backend/app/Mail/LoanQrCode.php`, `desktop/main.js` (tunnel manager),
  `desktop/scripts/prepare-cloudflared.ps1`, dan test `LoanQrCodeMailTest.php`.

### Pembaruan Otomatis (Auto-Update)

Sejak versi **1.0.3**, aplikasi desktop yang terinstal dapat memperbarui **otomatis**
dari GitHub Releases — tidak perlu install ulang manual setiap kali ada perbaikan:

1. Saat aplikasi dibuka, di latar belakang aplikasi periksa versi baru di GitHub
   (ulangi periodik setiap 4 jam; juga bisa di-trigger via **Aplikasi → Periksa Pembaruan**).
2. Bila versi baru tersedia → installer diunduh **di latar belakang** tanpa berhenti
   kerja petugas; status tersedia via menu **Aplikasi → Instal Pembaruan**.
3. Bila download selesai → aplikasi menampilkan **popup "Pembaruan Siap"** dengan dua pilihan:
   **Restart Sekarang** (instal otomatis via `quitAndInstall`) atau **Nanti**
   (instal terjadi otomatis saat aplikasi ditutup). Data
   (`%APPDATA%\Peminjaman Barang PNP`) tetap bertahan tanpa hilang.
4. Log pembaruan ditulis ke `%APPDATA%\Peminjaman Barang PNP\update.log`.

> **Instal pertama:** versi ≤1.0.2 yang sudah terinstal belum berisi modul pembaruan,
> jadi perlu diinstal ulang **manual** ke 1.0.3 sebanyak satu kali. Setelah itu semua
> versi berikutnya dapat diinstal otomatis dari GitHub.

Publikasi versi baru (untuk developer):

```powershell
cd frontend
npm run build
cd ../desktop
npm install
npm run dist:publish      # memerlukan GH_TOKEN di lingkungan (Personal Access Token)
```

> `npm run dist:publish` mengunduh `cloudflared`, build installer, lalu **upload otomatis**
> `Setup*.exe`, `latest.yml`, dan `*.exe.blockmap` sebagai GitHub Release (bila `GH_TOKEN`
> diset). Upload manual juga boleh: buat Release/tag `v1.0.4` dan upload ketiga file dari
> `desktop/release/`. File `latest.yml` wajib hadir agar versi lama bisa deteksi pembaruan.

```powershell
cd desktop
npm install
npm start                                  # mode pengembangan (php di PATH)
powershell -ExecutionPolicy Bypass -File scripts\prepare-php.ps1   # unduh PHP portable
npm run dist                               # build installer ke desktop/release/
node scripts/smoke-test.mjs                # uji pipeline tanpa GUI
```

Detail arsitektur dan perilaku first-run (migrasi otomatis, akun seed `admin`/`password`,
penyimpanan data di AppData) dibaca di [`desktop/README.md`](desktop/README.md).
