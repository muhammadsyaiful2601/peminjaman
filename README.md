# Sistem Informasi Peminjaman Barang Kampus

Sistem digital berbasis web untuk mengelola inventaris, alur peminjaman, dan pengembalian fasilitas/barang kampus dengan verifikasi identitas visual (foto wajah) dan teknologi QR Code.

## Fitur Utama

- **2 Peran Pengguna**: Petugas Utama (Admin), Asisten Petugas
- **Manajemen Barang**: CRUD barang & kategori dengan stok real-time
- **Peminjaman oleh Petugas**: Petugas mendaftarkan peminjaman untuk mahasiswa (tanpa login mahasiswa)
- **Verifikasi Foto**: Petugas memfoto wajah mahasiswa yang meminjam
- **Form Data Peminjam**: Mahasiswa mengisi data langsung di komputer petugas (nama, email, telepon, NIM)
- **QR Code Otomatis via Email**: QR Code transaksi dikirim ke email mahasiswa secara otomatis
- **Scan QR**: Verifikasi peminjaman & pengembalian dengan pemindai QR
- **Manajemen User**: Admin dapat menambah/mengedit/menghapus akun asisten
- **Laporan Transaksi**: Riwayat lengkap dengan status & kondisi barang

## Teknologi

| Layer | Teknologi |
| :--- | :--- |
| **Frontend** | React 19, Tailwind CSS v4, Vite, Lucide React, HTML5-Qrcode, qrcode.react |
| **Backend** | Laravel 13, Laravel Sanctum, Simple QR Code |
| **Database** | MySQL 8.4+ / 9.x |

## Struktur Proyek

```
peminjaman barang/
├── backend/          # Laravel 13 REST API
│   ├── app/
│   │   ├── Http/Controllers/Api/   # API Controllers
│   │   ├── Http/Middleware/        # Role middleware
│   │   ├── Mail/                   # LoanQrCode Mailable
│   │   └── Models/                 # User, Item, Loan
│   ├── database/
│   │   ├── migrations/             # Database schema
│   │   └── seeders/                # Seed data
│   ├── resources/views/emails/     # Email templates
│   └── routes/api.php              # API routes
└── frontend/         # React 19 SPA
    └── src/
        ├── api/                    # Axios client
        ├── components/             # Layout components
        ├── context/                # Auth context
        └── pages/                  # Page components
```

## Persyaratan

- PHP 8.4+
- Composer 2.x
- Node.js 20+
- MySQL 8.4+ / 9.x

## Instalasi & Menjalankan

### 1. Setup Database

Buat database MySQL:

```sql
CREATE DATABASE peminjaman_barang CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 2. Backend (Laravel)

```bash
cd backend

# Install dependencies
composer install

# Konfigurasi .env (sesuaikan kredensial database)
cp .env.example .env
# Edit .env: DB_CONNECTION=mysql, DB_DATABASE=peminjaman_barang, DB_USERNAME=root, DB_PASSWORD=

# Generate key & setup
php artisan key:generate
php artisan storage:link

# Migrasi & seed data
php artisan migrate:fresh --seed

# Jalankan server
php artisan serve --port=8000
```

### 3. Frontend (React)

```bash
cd frontend

# Install dependencies
npm install

# Jalankan dev server
npm run dev
```

Akses aplikasi di: **http://localhost:5173**

## Akun Demo

| Role | Email | Password |
| :--- | :--- | :--- |
| **Petugas Utama** | `admin@kampus.ac.id` | `password` |
| **Asisten Petugas** | `asisten@kampus.ac.id` | `password` |

> **Catatan**: Mahasiswa tidak perlu login. Petugas yang mendaftarkan peminjaman.

## Alur Kerja

1. **Mahasiswa** berbicara langsung ke petugas & memilih barang
2. **Petugas** mencari stok barang di sistem
3. **Petugas** memfoto wajah mahasiswa sebagai verifikasi identitas
4. **Mahasiswa** mengisi form data (nama, email, telepon, NIM) di komputer petugas
5. **Sistem** menerbitkan QR Code & mengirimkannya via email ke mahasiswa secara otomatis (status: Pending)
6. **Petugas** scan QR Code → verifikasi foto & data → setujui (status: Dipinjam, stok berkurang)
7. **Pengembalian**: Mahasiswa tunjukkan QR → petugas scan → cek kondisi barang (Bagus/Rusak/Hilang) → selesai (status: Dikembalikan, stok bertambah)

## API Endpoints

| Method | Endpoint | Deskripsi | Akses |
| :--- | :--- | :--- | :--- |
| POST | `/api/login` | Login | Public |
| POST | `/api/logout` | Logout | Auth |
| GET | `/api/user` | Data user saat ini | Auth |
| GET | `/api/items` | Daftar barang | Auth |
| POST | `/api/items` | Tambah barang | Admin, Asisten |
| PUT | `/api/items/{id}` | Update barang | Admin, Asisten |
| DELETE | `/api/items/{id}` | Hapus barang | Admin, Asisten |
| GET | `/api/loans` | Daftar peminjaman | Auth |
| POST | `/api/loans` | Buat peminjaman (petugas) | Admin, Asisten |
| GET | `/api/loans/{id}` | Detail peminjaman | Auth |
| GET | `/api/loans/qr/{uuid}` | Cari transaksi via UUID | Auth |
| POST | `/api/loans/{id}/approve` | Setujui peminjaman | Admin, Asisten |
| POST | `/api/loans/{id}/reject` | Tolak peminjaman | Admin, Asisten |
| POST | `/api/loans/{id}/return` | Proses pengembalian | Admin, Asisten |
| GET | `/api/users` | Daftar user | Admin |
| POST | `/api/users` | Tambah user | Admin |
| PUT | `/api/users/{id}` | Update user | Admin |
| DELETE | `/api/users/{id}` | Hapus user | Admin |