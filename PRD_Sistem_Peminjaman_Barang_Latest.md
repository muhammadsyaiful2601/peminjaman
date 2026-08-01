# Product Requirement Document (PRD)
## Sistem Informasi Peminjaman Barang Kampus

---

## 1. Ringkasan Eksekutif (Executive Summary)

Sistem Peminjaman Barang Kampus adalah platform digital berbasis web yang dirancang untuk mengelola inventaris, alur peminjaman, serta pengembalian fasilitas/barang kampus secara terstruktur. Sistem ini mengintegrasikan verifikasi identitas visual (foto wajah/KTM) dan teknologi **QR Code** untuk mempercepat proses validasi saat peminjaman dan pengembalian barang.

---

## 2. Tujuan & Sasaran (Objectives & Goals)

* **EFISIENSI VERIFIKASI:** Mempercepat alur *check-in* (peminjaman) dan *check-out* (pengembalian) menggunakan pemindaian QR Code.
* **AKUNTABILITAS & KEAMANAN:** Memastikan validitas peminjam melalui foto bukti sebelum peminjaman diproses.
* **DELEGASI TUGAS:** Memungkinkan Petugas Utama menambahkan Asisten Petugas untuk membantu operasional inventaris harian.
* **TRANSPARANSI STOK:** Menyediakan status ketersediaan barang secara real-time.

---

## 3. Peran Pengguna (User Roles & Permissions)

| Peran (Role) | Hak Akses (Permissions) |
| :--- | :--- |
| **Petugas Utama (Admin)** | • Kelola akun Asisten Petugas (Tambah/Edit/Hapus)<br>• Full Control CRUD data barang & kategori<br>• Verifikasi & kelola transaksi peminjaman/pengembalian<br>• Akses laporan & analitik lengkap |
| **Asisten Petugas** | • Kelola data barang (tambah/update stok)<br>• Scan QR Code peminjaman & pengembalian di lokasi<br>• Verifikasi kondisi barang saat dikembalikan |
| **Peminjam (Mahasiswa/Dosen)** | • Mendaftar & mengelola profil<br>• Melihat katalog barang & ketersediaan stok<br>• Mengajukan peminjaman + ambil foto verifikasi<br>• Mendapatkan & menyimpan QR Code peminjaman |

---

## 4. Alur Kerja & Fitur Utama (Core Workflows)

```text
[ Peminjam ] ──► Pengajuan Peminjaman + Ambil Foto ──► QR Code Terbit (Status: Pending)
                                                                │
[ Petugas / Asisten ] ◄── Scan QR Code + Verifikasi ────────────┘
         │
         ├──► Disetujui (Barang Diserahkan)
         │
         └──► Pengembalian: Peminjam Tunjukkan QR ──► Scan & Verifikasi Kondisi Barang
```

### 4.1. Manajemen Pengguna & Delegasi Petugas
* **Tambah Asisten Petugas:** Petugas Utama dapat membuatkan akun untuk Asisten Petugas (Input: Nama, Email, NIM/NIP, Password).
* **Manajemen Role:** Sistem memisahkan otorisasi antara Petugas Utama, Asisten Petugas, dan Peminjam.

### 4.2. Pengajuan Peminjaman & Verifikasi Foto
1. Peminjam memilih barang dan tentukan durasi peminjaman.
2. Peminjam diwajibkan melakukan **pembidikan foto langsung (Camera Capture)** melalui kamera perangkat/webcam sebagai bukti identitas visual.
3. Setelah pengajuan dikirim, sistem secara otomatis menghasilkan **QR Code Unik** yang menampung ID Transaksi.
4. Status awal transaksi: `Pending / Menunggu Identifikasi`.

### 4.3. Verifikasi & Pengambilan Barang (Scan QR)
1. Peminjam datang ke ruang inventaris dan menunjukkan QR Code kepada Petugas/Asisten.
2. Petugas/Asisten memindai QR Code menggunakan alat pemindai (scanner/kamera web).
3. Sistem menampilkan detail peminjaman, foto identitas peminjam, dan daftar barang.
4. Petugas/Asisten melakukan konfirmasi dan mengubah status menjadi `Dipinjam`.

### 4.4. Alur Pengembalian Barang (Scan QR)
1. Peminjam menunjukkan QR Code transaksi yang sama saat mengembalikan barang.
2. Petugas/Asisten memindai QR Code untuk membuka data transaksi.
3. Petugas memeriksa kondisi fisik barang (Bagus / Rusak / Hilang) dan mencatat catatan kondisi jika ada.
4. Petugas menyelesaikan transaksi → Status berubah menjadi `Dikembalikan` dan stok barang bertambah otomatis.

---

## 5. Spesifikasi Arsitektur Teknologi (Versi Terbaru)

```text
┌────────────────────────────────────────────────────────┐
│               React 19 + Tailwind CSS v4               │
│             (Inertia.js / REST API + Axios)            │
└───────────────────────────┬────────────────────────────┘
                            │ HTTP Request / JSON
┌───────────────────────────▼────────────────────────────┐
│                      Laravel 13                        │
│             (Authentication, API, Logic)               │
└───────────────────────────┬────────────────────────────┘
                            │ SQL Queries
┌───────────────────────────▼────────────────────────────┐
│                   MySQL (Latest 9.x/8.4 LTS)           │
└────────────────────────────────────────────────────────┘
```

* **Frontend:** React 19, Tailwind CSS v4, Lucide React (Icons), HTML5 Camera API, Library Scanner QR Code versi terbaru (HTML5-Qrcode).
* **Backend:** Laravel 13, Laravel Sanctum / Inertia.js adapter terbaru, Package QR Code yang kompatibel dengan Laravel 13.
* **Database:** MySQL versi terbaru (9.x atau 8.4 LTS).
* **Lingkungan Pengembangan:** Sistem ini dirancang untuk alur kerja yang rapi. Pengembang cukup menyiapkan eksekusi toolchain `npm run dev` secara paralel dengan `php artisan serve`. Seluruh file template *default* bawaan framework yang tidak terpakai direkomendasikan untuk dihapus sejak awal guna menjaga kode proyek tetap bersih dan terorganisir.

---

## 6. Perancangan Skema Basis Data (MySQL Schema)

### `users`
| Column | Type | Constraints / Description |
| :--- | :--- | :--- |
| `id` | BigInt | Primary Key, Auto Increment |
| `name` | String | Nama Lengkap |
| `email` | String | Unique |
| `password` | String | Hashed |
| `role` | Enum | `'admin'`, `'assistant'`, `'borrower'` |
| `created_at` | Timestamp | |

### `items`
| Column | Type | Constraints / Description |
| :--- | :--- | :--- |
| `id` | BigInt | Primary Key, Auto Increment |
| `item_code` | String | Unique (Kode Inventaris) |
| `name` | String | Nama Barang |
| `category` | String | Kategori (misal: Elektronik, Olahraga) |
| `stock` | Integer | Jumlah Stok Tersedia |
| `image` | String | Path Gambar Barang |

### `loans` (Transaksi Peminjaman)
| Column | Type | Constraints / Description |
| :--- | :--- | :--- |
| `id` | BigInt | Primary Key, Auto Increment |
| `uuid` | UUID | Unique (Payload QR Code) |
| `user_id` | Foreign Key | References `users.id` |
| `item_id` | Foreign Key | References `items.id` |
| `qty` | Integer | Jumlah dipinjam |
| `borrow_photo` | String | Path Foto Wajah/KTM Peminjam |
| `status` | Enum | `'pending'`, `'borrowed'`, `'returned'`, `'rejected'` |
| `borrowed_at` | DateTime | Tanggal/Jam Disetujui Peminjaman |
| `returned_at` | DateTime | Tanggal/Jam Dikembalikan |
| `verified_by` | Foreign Key | References `users.id` (Petugas/Asisten yang memverifikasi) |
| `condition_on_return` | String | Catatan kondisi barang saat dikembalikan |

---

## 7. Persyaratan Non-Fungsional (Non-Functional Requirements)

1. **Responsivitas UI:** Tampilan frontend harus sepenuhnya responsif (mobile & desktop) menggunakan Tailwind CSS v4.
2. **Keamanan:** 
   * Akses endpoint disesuaikan dengan role melalui middleware Laravel 13.
   * Penyimpanan foto peminjam dan data sensitif diisolasi pada repositori media tertutup/terproteksi.
3. **Kecepatan Pembacaan QR:** Library pemindai QR Code di frontend harus dapat memproses pemindaian dengan respons time kurang dari 1 detik pada resolusi kamera standar.
