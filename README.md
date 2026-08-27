# Sistem Informasi Peminjaman Barang Kampus

Aplikasi web untuk inventaris, peminjaman multi-barang, pengembalian, verifikasi foto, dan bukti transaksi berbasis QR Code. Mahasiswa tidak membuat akun. Petugas memasukkan data peminjam dan menyerahkan barang melalui aplikasi.

## Ringkasan Sistem

- Frontend React SPA untuk dashboard petugas.
- Backend Laravel REST API.
- Autentikasi petugas menggunakan Laravel Sanctum bearer token.
- Satu transaksi dapat berisi beberapa jenis barang dan jumlah unit yang berbeda.
- Stok dikurangi ketika transaksi dibuat, bukan ketika disetujui.
- Bukti peminjaman dan pengembalian dikirim melalui email.
- PDF berisi data peminjam, foto verifikasi, seluruh barang, QR Code, logo kampus, dan logo SI.
- Laporan peminjaman dapat difilter, dicetak sebagai dokumen resmi, dan diunduh sebagai PDF.
- Peminjaman resmi mendukung peminjaman skala besar dengan surat PDF dan banyak barang.
- Waktu aplikasi menggunakan WIB (`Asia/Jakarta`).

## Fitur

### Inventaris

Petugas dapat melihat daftar barang, mencari berdasarkan nama/kode/kategori, dan melihat stok dengan pagination. Admin dan asisten dapat menambah, mengubah, dan menghapus barang. Setiap barang memiliki kode unik, nama, kategori, stok, dan gambar opsional.

### Peminjaman Multi-Item

Saat membuat peminjaman, petugas dapat menambahkan beberapa barang. Setiap baris memiliki `item_id` dan `qty`. Barang yang sama tidak boleh muncul dua kali dalam satu transaksi.

Data peminjam:

- Nama dan email wajib.
- Nomor telepon dan NIM/NIP opsional.
- Foto peminjam wajib diambil melalui kamera browser.

Pembuatan transaksi berjalan dalam database transaction. Semua stok dikunci dan dicek terlebih dahulu; jika salah satu barang tidak cukup, seluruh transaksi ditolak. Jika berhasil, status langsung menjadi `borrowed`, stok berkurang, QR Code dikirim melalui email, dan `borrowed_at` diisi.

### Pengembalian

Petugas mencari transaksi melalui scan QR, UUID, kode peminjaman, atau upload PDF. Pengembalian hanya dapat dilakukan untuk transaksi berstatus `borrowed`.

Petugas wajib memilih kondisi `bagus`, `rusak`, atau `hilang`. Catatan kondisi opsional. Frontend meminta foto bukti pengembalian, kemudian sistem mengubah status menjadi `returned`, mengembalikan stok seluruh item transaksi, dan mengirim email konfirmasi.

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

Halaman `/reports` menyediakan laporan transaksi dengan filter tanggal dan status. Laporan menampilkan ringkasan jumlah transaksi serta tabel detail peminjaman. Nama penandatangan dan NIP dapat diisi, tersimpan otomatis di browser, dan dicantumkan pada dokumen.

Laporan dapat:

- Dicetak langsung sebagai dokumen resmi dengan kop Politeknik Negeri Padang, Jurusan Teknologi Informasi, dan Program Studi Sistem Informasi.
- Diunduh sebagai PDF melalui backend Dompdf.
- Memuat periode laporan, tanggal cetak, tabel bergaris, nama penandatangan, dan NIP.

Saat mencetak langsung dari browser, nonaktifkan opsi **Headers and footers** pada dialog print agar URL dan metadata browser tidak ikut tercetak.

### Peminjaman Resmi Skala Besar

Halaman `/loans/official` digunakan petugas untuk membuat peminjaman resmi yang terdiri dari banyak jenis barang dan jumlah unit. Form menyediakan data peminjam, NIM mahasiswa opsional, tujuan kegiatan, periode peminjaman, serta nama dan NIP penandatangan. Setelah dikirim, sistem memeriksa stok secara atomik, membuat transaksi peminjaman, mengurangi stok, dan mengunduh surat resmi dalam format PDF.

Data transaksi peminjam tersimpan pada daftar peminjaman. Setelah surat dibuat, tombol **Proses Barang Kembali** membuka detail transaksi. Petugas dapat mengambil foto bukti, memilih kondisi barang, dan sistem mengirim surat bukti pengembalian ke email peminjam.

## Alur Operasional

1. Petugas login.
2. Petugas memilih satu atau lebih barang dan memasukkan jumlah masing-masing.
3. Petugas memasukkan data mahasiswa.
4. Petugas mengambil foto peminjam melalui kamera.
5. Sistem mengecek stok semua barang dalam satu transaksi.
6. Sistem mengurangi stok, membuat transaksi berstatus `borrowed`, dan mengirim email QR.
7. Mahasiswa menyimpan PDF/QR atau kode peminjaman.
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
    app/Mail/                     Email peminjaman/pengembalian
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
    src/pages/                     Dashboard, barang, peminjaman resmi, laporan, scan, user, profil
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
APP_NAME="Sistem Peminjaman Barang"
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
MAIL_FROM_NAME="Sistem Peminjaman Barang"
```

Pada konfigurasi contoh, mailer dapat menggunakan `log`; email hanya ditulis ke log Laravel dan tidak dikirim ke penerima.

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