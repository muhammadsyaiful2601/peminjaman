# Sistem Peminjaman Barang — Jurusan Teknologi Informasi, Politeknik Negeri Padang

Aplikasi untuk **inventaris**, **peminjaman multi-barang**, **pengembalian dengan verifikasi foto & kondisi**,
**peminjaman resmi (surat)**, **laporan resmi**, dan **bukti transaksi berbasis QR Code + email**.
Mahasiswa/peminjam **tidak membuat akun** — petugas memasukkan data peminjam dan menyerahkan barang melalui aplikasi.

> **Versi aplikasi:** lihat `desktop/package.json` dan halaman **Releases**
> repositori ini. Installer `.exe`
> tidak disimpan di source — unduh dari halaman **Releases** repositori ini.
>
> **Status kepemilikan:** perangkat lunak **proprietary** (bukan open source, bukan MIT).
> Dihibahkan untuk Politeknik Negeri Padang dan dilisensikan secara komersial untuk
> institusi lain — lihat [Lisensi dan Kepemilikan](#lisensi-dan-kepemilikan) dan
> [`LICENSE.md`](LICENSE.md).

## Lisensi dan Kepemilikan

Sistem ini **bukan open source**. Hak cipta dan hak lisensi sepenuhnya dipegang
pengembang. Ketentuan lengkap ada di [`LICENSE.md`](LICENSE.md); ringkasannya:

| Pemakai | Dasar | Biaya | Jangka waktu |
| :--- | :--- | :--- | :--- |
| Politeknik Negeri Padang — Jurusan Teknologi Informasi | Hibah, non-eksklusif, tidak dipindahtangankan | Gratis | Tanpa batas waktu |
| Institusi, perusahaan, atau perorangan lain | Perjanjian lisensi tertulis (invoice + perjanjian) | Sesuai perjanjian | Sesuai perjanjian |

Yang perlu diperhatikan:

- Source code di repositori ini dipublikasikan untuk keperluan pengembangan dan
  transparansi, **bukan** pemberian lisensi. Menyalin, menjual, atau menerbitkan ulang
  tanpa izin tertulis tidak diizinkan.
- Dilarang menghapus nama pengembang, pemberitahuan hak cipta, dan daftar komponen
  pihak ketiga.
- Komponen pihak ketiga (Laravel, React, Electron, PHP, `cloudflared`, dan lainnya) tetap
  tunduk pada lisensinya masing-masing — daftar lengkap ada di `LICENSE.md` bagian 5.
- Data transaksi adalah milik institusi pengguna dan disimpan pada infrastruktur
  pengguna sendiri (SQLite/MySQL); pengembang tidak menarik data tersebut.

## Pilih Mode Deployment

Repository ini menyediakan dua mode penggunaan:

| Mode | Cocok untuk | Database | Server eksternal | Unduh bukti dari email |
| :--- | :--- | :--- | :--- | :--- |
| Website | Dipakai banyak komputer melalui jaringan/domain | MySQL production | PHP web server + database | Tautan publik selalu (domain hosting) |
| Desktop Windows | Dipakai offline pada satu komputer petugas | SQLite lokal | Tidak perlu Laragon/XAMPP/MySQL | PDF lampiran selalu; tautan publik otomatis saat komputer online (Cloudflare Quick Tunnel, tanpa VPS/hosting) |

Website dan desktop memakai source frontend, API, migration, seeder, dan aturan bisnis yang sama. Perbedaannya hanya pada cara menjalankan backend dan database.

## Daftar Isi

- [Lisensi dan Kepemilikan](#lisensi-dan-kepemilikan)
- [Pilih Mode Deployment](#pilih-mode-deployment)
- [Ringkasan Sistem](#ringkasan-sistem)
- [Fitur](#fitur)
- [Alur Operasional](#alur-operasional)
- [Teknologi](#teknologi)
- [Struktur Direktori](#struktur-direktori)
- [Persyaratan](#persyaratan)
- [Menjalankan di Lokal](#menjalankan-di-lokal)
- [Konfigurasi Environment](#konfigurasi-environment)
- [Akun Seed Default](#akun-seed-default)
- [API](#api)
- [Perintah Verifikasi](#perintah-verifikasi)
- [Deploy Website Production](#deploy-website-production)
- [Build dan Instalasi Desktop Windows](#build-dan-instalasi-desktop-windows)
- [Tutorial Mandiri untuk Komputer Lain](#tutorial-mandiri-untuk-komputer-lain)
- [Publikasi ke GitHub](#publikasi-ke-github)
- [Keamanan Secret](#keamanan-secret)
- [Checklist Deployment](#checklist-deployment)
- [Aplikasi Desktop (Electron)](#aplikasi-desktop-electron)
  - [Unduh bukti publik saat komputer petugas online](#unduh-bukti-publik-saat-komputer-petugas-online-tanpa-vpshosting)
  - [Pembaruan Otomatis (Auto-Update)](#pembaruan-otomatis-auto-update)

> **Catatan versi:** dokumen ini tidak menuliskan nomor versi installer agar tidak
> tertinggal saat rilis baru. Acuan versi aplikasi adalah `desktop/package.json`,
> sedangkan installer terbaru tersedia pada halaman **Releases**.

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
- Versi desktop **1.0.3+** dapat mendeteksi pembaruan dari GitHub Releases di latar
  belakang (cek saat aplikasi dibuka dan tiap 4 jam); pengunduhan dipicu pengguna,
  lalu aplikasi memasang versi baru secara senyap dan memulai ulang otomatis.

## Fitur

### Jenis Peminjam (Mahasiswa, Tendik, Dosen, Umum)

Aplikasi tidak hanya melayani mahasiswa. Satu halaman **Data Peminjam**
menggantikan halaman Data Mahasiswa lama dan memuat seluruh peminjam dengan
tab per jenis:

| Jenis | Identitas | Keterangan |
| :--- | :--- | :--- |
| **Mahasiswa** | NIM | Jenis bawaan, dipakai seluruh data lama |
| **Tendik** | NIP | Tenaga pendidik & kependidikan PNP |
| **Dosen** | NIP | Dosen PNP |
| **Umum** | Nomor identitas | Peminjam dari luar kampus |

Setiap jenis juga menyimpan **Jabatan / Unit Kerja** (jabatan pegawai, program
studi, atau unit kerja) yang boleh dikosongkan.

- Tab menampilkan **jumlah data** pada masing-masing jenis, dan angkanya ikut
  mengikuti kata kunci pencarian.
- Kolom **Jenis** tampil sebagai badge berwarna di tabel Data Peminjam, daftar
  Peminjaman, detail transaksi, dan halaman Surat Bebas Labor.
- Kolom identitas menyesuaikan otomatis: `NIM` untuk mahasiswa, `NIP` untuk
  tendik/dosen, dan `Nomor Identitas` untuk peminjam umum.
- Form **Buat Peminjaman** punya pemilih jenis; daftar peminjam tersimpan yang
  muncul hanya berisi jenis yang dipilih, dan memilih salah satu otomatis
  mengisi nama/email/NIP beserta jenisnya.
- **Surat bebas labor** otomatis berlaku untuk pegawai: pencocokan transaksi
  lama tetap memakai NIM/NIP, email, nama, atau nomor telepon, dan peminjam
  yang tidak terdaftar tetap tampil sebagai "Peminjam manual".

Data pegawai disimpan pada tabel yang sama dengan mahasiswa (tetap `students`),
sehingga seluruh alur yang sudah ada ikut memakainya tanpa perubahan.

Parameter baru pada API:

| Endpoint | Parameter | Keterangan |
| :--- | :--- | :--- |
| `GET /api/students` | `type` | Filter jenis: `mahasiswa`, `tendik`, `dosen`, atau `umum`. Kosong = semua. |
| `GET /api/students` | — | `meta.by_type` berisi jumlah per jenis (ikut memperhitungkan `search`). |
| `POST`/`PUT /api/students` | `type`, `position` | Jenis dan jabatan/unit kerja. `type` opsional — bila tidak dikirim, jenis lama dipertahankan. |
| `POST /api/loans` | `borrower_type` | Jenis peminjam pada transaksi. Opsional; kosong dibaca sebagai `mahasiswa`. |

Impor spreadsheet (CSV/XLSX/Google Sheets) menerima dua kolom baru yang
**opsional** — `Jenis` dan `Jabatan / Unit Kerja`. Kolom `Jenis` mengenali juga
istilah lain seperti `Eddik`, `Mhs`, `dosen`, atau `masyarakat`. Kolom kosong
berarti mahasiswa, dan spreadsheet lama tanpa kedua kolom ini tetap bisa
diimpor. Jenis yang tidak dikenali akan ditolak dengan pesan yang jelas, bukan
disimpan diam-diam sebagai mahasiswa.

Daftar istilah yang dikenali pada kolom `Jenis` ada di
[`backend/app/Support/BorrowerType.php`](backend/app/Support/BorrowerType.php).

### Spreadsheet per Kelompok (Google Sheets)

Mahasiswa, **tendik**, dan **dosen** masing-masing punya spreadsheet Google
Sheets sendiri dengan **logika yang persis sama** seperti mahasiswa: tautan
CSV terpublikasi, impor otomatis saat aplikasi dibuka lalu diulang tiap
5 menit, tombol **Refresh Data**, dan penanda waktu sinkron terakhir. Peminjam
umum tidak memakai spreadsheet (dicatat manual lewat form).

Cara memakai:

1. Buat spreadsheet untuk tiap kelompok (**File → Bagikan → Publikasikan ke
   web** → format CSV), lalu salin URL-nya.
2. Buka **Data Peminjam → Impor Spreadsheet**, pilih kelompoknya
   (Mahasiswa / Tendik / Dosen), lalu tempel URL tersebut.
3. Desde itu tautan otomatis ditarik tiap 5 menit. Status tiap kelompok
   terlihat di bawah tombol Refresh Data.

Kolom yang dikenali di spreadsheet: `NIM/NIP`, `Nama`, `Email`,
`No. Telepon` (wajib), serta `Jenis` dan `Jabatan / Unit Kerja` (opsional).
**Begitu spreadsheet ditautkan ke suatu kelompok, seluruh barisnya otomatis
disimpan sebagai kelompok itu** sehingga kolom `Jenis` tidak perlu diisi di
file tendik/dosen.

| Jenis | Kunci `app_settings` (URL) | Kunci (waktu sinkron) |
| :--- | :--- | :--- |
| Mahasiswa | `student_sync_csv_url` | `student_sync_last_at` |
| Tendik | `tendik_sync_csv_url` | `tendik_sync_last_at` |
| Dosen | `dosen_sync_csv_url` | `dosen_sync_last_at` |

Kunci mahasiswa sengaja memakai nama lamanya, jadi spreadsheet yang sudah
tertaut tidak perlu disetel ulang. Setiap kelompok berjalan berurutan (bukan
bersamaan) supaya server tidak terbebani, dan hasilnya digabung jadi satu
pesan — bila satu kelompok gagal, hasil kelompok lain tetap tersimpan dan
petugas diberi tahu kelompok mana yang gagal.

Endpoint yang memakai `type`:

| Endpoint | Parameter | Keterangan |
| :--- | :--- | :--- |
| `GET /api/students/import/source` | `type` | URL + waktu sinkron kelompok tersebut. Tanpa `type` = mahasiswa. |
| `POST /api/students/import/source` | `url`, `type` | Simpan URL kelompok tersebut. |
| `POST /api/students/import/csv-url` | `url`, `type` | Tarik CSV, paksa jenis, lalu simpan URL-nya. |
| `POST /api/students/import` | `file`, `type` | Impor berkas; `type` memaksa jenis seluruh baris. |

### Penomoran & Paginasi Tabel

Seluruh tabel dan daftar data di aplikasi memakai aturan yang sama:

- Kolom pertama tabel adalah **No.** berisi nomor urut yang **berlanjut antar halaman**
  (halaman 2 mulai dari 11, 12, 13, ...), sehingga petugas bisa merujuk baris
  tertentu tanpa ambigu.
- Tabel menampilkan **10 data per halaman**. Begitu data melewati 10 baris, tombol
  **Berikutnya** (dan **Sebelumnya**) otomatis muncul di bawah tabel, disertai
  ringkasan "Menampilkan 11 - 20 dari 37 data".
- Tabel yang hanya berisi satu halaman tidak menampilkan tombol apa pun, agar
  tampilan tetap ringkas.
- Mengubah kata kunci pencarian atau filter mengembalikan tampilan ke halaman 1,
  dan menghapus baris terakhir pada halaman akhir otomatis mundur satu halaman
  supaya tabel tidak tampil kosong.
- Tabel **Laporan Peminjaman** tetap dicetak lengkap: paginasi hanya berlaku di
  layar, hasil cetak (dan PDF) memuat seluruh transaksi hasil filter.

Komponen yang dipakai bersama:

| Berkas | Peran |
| :--- | :--- |
| `frontend/src/components/TablePagination.jsx` | Tombol Sebelumnya/Berikutnya + ringkasan data |
| `frontend/src/hooks/useTablePagination.js` | Paginasi sisi-klien + `rowOffset` untuk kolom No. |

Endpoint yang memakai paginasi server: `/api/loans`, `/api/users`, `/api/items`,
dan `/api/students`. `GET /api/students` menerima `page` dan `per_page` sebagai
parameter **opsional** — bila `per_page` tidak dikirim, seluruh data tetap
dikembalikan agar form peminjaman yang membutuhkan semua mahasiswa tetap bekerja.

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

Pengaturan berada di **Manajemen -> Pengaturan Sistem**, tab **Hosting & Sinkronisasi** (khusus
aplikasi desktop), berisi:

- Form koneksi hosting: **Host/Link, Port, Nama Database, Username, Password** MySQL.
- **Simpan Konfigurasi** / **Tes Koneksi** — menyimpan konfigurasi, atau menguji koneksi ke MySQL hosting.
- **Migrasi Data ke Hosting** — membuat skema di MySQL (menjalankan migrasi Laravel pada
  koneksi hosting) lalu menyalin seluruh data lokal sebagai sinkronisasi awal.
- **Sinkron Sekarang** — sinkronisasi dua arah manual kapan saja.
- **Aktifkan/Matikan Otomatis** — mengaktifkan sinkronisasi otomatis **setiap minggu** (7 hari);
  aplikasi desktop memicu pemeriksaan berkala (tiap 6 jam) dan backend menentukan sendiri
  apakah sudah jatuh tempo, sehingga aman bila komputer sempat mati.

> **Prasyarat PHP:** mode hybrid butuh ekstensi `pdo_mysql` pada runtime PHP yang
> dibundel aplikasi. Ekstensi ini **sudah aktif** lewat `desktop/assets/php.ini`.
> Bila muncul `Gagal terhubung: could not find driver`, berarti `pdo_mysql` tidak
> termuat — periksa baris `extension=pdo_mysql` pada `php.ini` di folder `php/`
> yang dipakai aplikasi, lalu jalankan ulang aplikasi. Untuk memeriksanya manual:
> `php -r "var_dump(PDO::getAvailableDrivers());"` — hasilnya harus memuat
> `mysql`, bukan hanya `sqlite`.

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
| Menerbitkan surat bebas labor | Ya | Ya |
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

### Data Mahasiswa

Menu **Data Mahasiswa** dipakai admin dan asisten untuk menyimpan NIM/NIP, nama,
email, dan nomor telepon mahasiswa. Data dapat dicari, ditambah, diubah, atau dihapus.

Saat membuat peminjaman, petugas cukup memilih mahasiswa tersimpan; nama, email,
nomor telepon, dan NIM/NIP otomatis terisi pada form. Pengisian manual tetap tersedia
untuk peminjam yang belum terdaftar. Data yang dikirim ke transaksi disimpan sebagai
snapshot, sehingga perubahan data mahasiswa tidak mengubah riwayat peminjaman.

Data mahasiswa juga dapat diimpor dari **spreadsheet** melalui tombol **Impor
Spreadsheet**: berkas CSV/XLSX/XLS, atau **CSV Google Sheets yang sudah dipublikasikan**
(*File → Bagikan → Publikasikan ke web*, format CSV). NIM/NIP dipakai sebagai kunci
utama dan email sebagai kunci cadangan, sehingga:

- baris dengan NIM yang sudah ada di sistem **diperbarui** (nama, email, telepon);
- perbaikan NIM pada spreadsheet (email tetap sama) ikut terpakai tanpa membuat data
  ganda;
- baris baru **ditambahkan**; baris yang isinya sama persis tidak dihitung sebagai
  perubahan (`unchanged`).

Selama halaman **Data Mahasiswa** terbuka, sistem menyinkronkan ulang sumber
spreadsheet secara otomatis setiap 5 menit (penghitung **Refresh otomatis dalam
m:ss** dan **Sinkron terakhir hh:mm** tampil di kanan judul halaman). Perubahan yang
dilakukan pada spreadsheet — nama, email, telepon, sampai NIM — langsung terpakai di
sistem, dan tabel diperbarui tanpa menampilkan ulang status "Memuat". Tombol
**Refresh Data** menjalankan sinkronisasi segera, sedangkan kegagalan sinkronisasi
(mis. sheet tidak lagi dipublikasikan, tab salah, atau jaringan mati) **ditampilkan
sebagai pesan kesalahan** agar data yang belum berubah selalu punya penjelasan.
Sumber tersimpan di database (`student_sync_csv_url`) beserta waktu sinkron terakhir
(`student_sync_last_at`).

Baris yang **dihapus** dari spreadsheet tidak menghapus data mahasiswa di sistem
(agar riwayat peminjaman/surat bebas labor tetap utuh); hapus data lewat tombol hapus
pada tabel.

### Bebas Labor

Halaman `/clearance` (menu **Bebas Labor**) dipakai petugas untuk menerbitkan **Surat Keterangan Bebas
Laboratorium** berkop surat. Daftar pada halaman ini memakai **data mahasiswa yang sama dengan menu Data
Mahasiswa**, lalu dilengkapi ringkasan peminjaman tiap orang:

1. Petugas mencari mahasiswa berdasarkan nama, NIM, atau email.
2. Setiap mahasiswa ditandai kelayakannya: **Bebas Labor** (semua barang yang pernah dipinjam sudah
   dikembalikan), **N belum kembali** (masih menahan barang), atau **Belum pernah meminjam**
   (belum ada riwayat peminjaman, tetap dihitung bebas labor).
3. Surat dapat diterbitkan untuk setiap peminjam yang **tidak memiliki tanggungan**. Mahasiswa yang
   **belum pernah meminjam** tetap memperoleh **Surat Keterangan Bebas Laboratorium**; isinya
   menerangkan bahwa tidak ada transaksi peminjaman yang tercatat atas namanya dan tabel rincian
   menuliskan *Tidak ada tanggungan peminjaman barang yang tercatat* (status tanggungan pada surat:
   *Tidak ada tanggungan (bebas labor)*). Transaksi berstatus `rejected` (ditolak) juga dianggap tidak
   pernah menerima barang, sehingga tetap bebas labor.
4. Bila seluruh barang sudah dikembalikan, surat diterbitkan sebagai **Surat Keterangan Bebas
   Laboratorium** (`surat-bebas-labor-<nama peminjam>.pdf`) yang menyatakan peminjam tidak memiliki
   tanggungan dan memuat daftar transaksi yang sudah dikembalikan.
5. Bila masih ada barang yang **belum dikembalikan** (status `borrowed` atau `pending`), tombol unduh
   tetap dapat dipakai, namun surat yang dihasilkan otomatis berupa **Surat Keterangan Tanggungan
   Peminjaman Laboratorium** (`surat-tanggungan-labor-<nama peminjam>.pdf`) berisi rincian barang
   yang belum dikembalikan, **bukan** pernyataan bebas labor. Peringatan dan daftar barang yang belum
   kembali juga tampil di halaman.
6. **Aksi Per Baris & Cetak Masal**:
   - Di sisi kanan setiap baris mahasiswa berstatus **Bebas Labor**, tersedia tombol langsung **Unduh** (PDF) dan **Cetak** (print dialog).
   - Di sisi kiri setiap baris mahasiswa bebas labor, terdapat kotak centang (checkbox) untuk pemilihan multi-mahasiswa.
   - Tersedia tombol **Cetak Masal** di toolbar atas untuk mencetak surat bebas labor beberapa mahasiswa sekaligus dalam satu sesi cetak.
   - Kotak centang, tombol Unduh baris, dan fitur Cetak Masal tersedia untuk semua peminjam **bebas labor**, termasuk mahasiswa yang belum pernah meminjam. Peminjam yang masih memiliki tanggungan tidak dapat dipilih/dicetak masal (ditolak HTTP 422 oleh backend).
7. Petugas mengisi keperluan surat (mis. *Persyaratan bebas pustaka*), tanggal surat, laboratorium
   (opsional), serta nama dan NIP penandatangan, lalu menekan
   **Buat & Unduh Surat**. Penandatangan dapat dipilih cepat dari daftar teknisi sehingga nama dan NIP
   terisi otomatis.

Identitas pada surat (nama, NIM/NIP, email) diambil dari **data mahasiswa** bila orang tersebut terdaftar,
sehingga perubahan nama/email pada data mahasiswa langsung terpakai tanpa mengubah riwayat peminjaman.
Peminjam manual yang belum terdaftar pada data mahasiswa tetap tampil pada daftar (ditandai *Peminjam
manual*) agar tanggungannya tetap terpantau, dengan identitas dari transaksi terbarunya.

Isi surat memuat kop instansi (nama kementerian, unit, instansi, alamat, jurusan), nomor surat,
identitas peminjam (nama, NIM/NIP, email, **keperluan surat**), status tanggungan, tabel transaksi,
pernyataan yang menyesuaikan status, serta kolom tanda tangan peminjam dan petugas laboratorium. Surat
tidak memuat jejak waktu cetak (*Dicetak dari sistem pada … WIB*) agar tampilannya tetap resmi dan tidak
lekas usang bila surat dicetak ulang. Surat bebas labor selalu menuliskan **tidak ada tanggungan** dan
tidak pernah memuat pernyataan/tabel tanggungan:

- Peminjam yang sudah mengembalikan seluruh barang: tabel rincian berisi transaksi yang sudah
  dikembalikan (kolom *Tanggal kembali*).
- Peminjam yang belum pernah meminjam: keterangan bahwa tidak ada transaksi peminjaman yang tercatat
  dan tabel rincian menuliskan *Tidak ada tanggungan peminjaman barang yang tercatat*.
- Peminjam yang masih menahan barang: surat otomatis menjadi Surat Keterangan Tanggungan dengan tabel
  barang yang belum dikembalikan (kolom *Tanggal pinjam*).

Transaksi dihubungkan ke data mahasiswa dengan mencocokkan **salah satu** data yang tercatat pada
transaksi: NIM, email, nama, atau nomor telepon (varian penulisan telepon seperti `+62`/`0` dan spasi
dianggap sama). Dengan begitu transaksi yang dibuat **sebelum data mahasiswa tersedia** tetap tampil pada
mahasiswanya dan tidak hilang dari halaman. Bila transaksi memuat NIM yang terdaftar, NIM tersebut
bersifat mengikat sehingga transaksi tidak salah menempel ke mahasiswa lain yang email/nama-nya kebetulan
sama. Transaksi yang tidak cocok dengan data mahasiswa mana pun tetap tampil sebagai **peminjam manual**
(dari data peminjaman, dikelompokkan per NIM atau email + nama). Transaksi berstatus `rejected` (ditolak)
tidak dihitung sebagai peminjaman.
Transaksi lama tanpa NIM milik orang yang sama tetap ikut dihitung agar surat tidak terbit saat barang
masih ditahan.

### Pengaturan Sistem

Semua konfigurasi aplikasi dikumpulkan di satu halaman: **Manajemen → Pengaturan Sistem**.
Halaman ini memakai tab, dan bagian khusus desktop otomatis disembunyikan pada versi website.

| Tab | Isi | Berlaku di |
| :--- | :--- | :--- |
| **Identitas Aplikasi** | Nama aplikasi, baris kop surat, nama instansi, deskripsi login, logo & foto halaman depan | Desktop & web |
| **Email (SMTP)** | Host, port, username, password, email & nama pengirim, plus **Kirim Email Tes** | Desktop (web: diatur lewat `.env`) |
| **Hosting & Sinkronisasi** | Koneksi MySQL hosting, migrasi, sinkron manual, sinkron otomatis | Desktop saja |
| **Backup & Pemulihan** | Unduh backup (lengkap / SQLite / MySQL) dan pemulihan data | Desktop & web |
| **Tentang & Pembaruan** | Versi aplikasi, kanal Stabil/Beta, periksa & pasang pembaruan | Desktop saja |

> Dulu pengaturan email hanya bisa diisi lewat jendela wizard terpisah saat pemasangan
> pertama, sehingga mudah terlewat bila wizard ditutup. Sekarang semuanya ada di tab
> **Email (SMTP)** yang sama dengan konfigurasi lain. Password SMTP yang tersimpan tidak
> pernah dikirim ke antarmuka — kolom password yang dikosongkan berarti password lama
> dipertahankan.

### Backup & Pemulihan Data

Halaman **Pengaturan Sistem** (menu admin) menyediakan dua hal: mengunduh backup dan memulihkan
(*restore*) data dari berkas backup.

**Mengunduh backup** — semua tombol meminta password admin terlebih dahulu:

| Tombol | Isi berkas | Kegunaan |
| :--- | :--- | :--- |
| **Backup Lengkap (Database + Foto)** | Arsip `.zip` berisi `database.sqlite`/`database.sql`, seluruh foto pada folder unggahan (`borrow-photos/`, `return-photos/`, `items/`, `branding/`), dan `manifest.json` | Cadangan menyeluruh; hasilnya dapat dipulihkan langsung dari aplikasi |
| **Download SQLite** / **Download MySQL** | Berkas database saja (`.sqlite` / `.sql`) | Cadangan cepat atau pemakaian lanjutan (mis. dipulihkan lewat alat lain) |

Semua nama berkas unduhan **memuat tanggal dan jam** backup, misalnya
`backup-lengkap-2026-09-28-142530.zip` (format `YYYY-MM-DD-HHmmss`). Dengan begitu:

- tidak ada lagi nama statis yang saling menimpa — setiap backup menjadi arsip tersendiri;
- mudah diurutkan dari yang terlama ke yang terbaru, dan langsung terbaca kapan backup dibuat;
- di aplikasi desktop, dialog simpan berkas sudah menyarankan nama itu sebagai bawaan.

**Memulihkan data (Impor Database)** — bagian **Impor / Pulihkan Database** pada halaman yang sama:

1. Pilih berkas backup (arsip `.zip` hasil Backup Lengkap, atau berkas `.sqlite`/`.sql`).
2. Masukkan password admin, lalu tekan **Pulihkan Data Sekarang** dan setujui konfirmasi.
3. Sistem memeriksa berkas terlebih dahulu (jenis database harus cocok dengan mode aplikasi, dan
   berkas harus benar-benar database aplikasi ini — ditandai adanya tabel `users` dan `items`).
   Bila tidak sesuai, pemulihan ditolak dan data lama tidak berubah.
4. Sebelum menimpa, sistem menyimpan salinan otomatis data saat ini di
   `storage/app/backups/sebelum-restore-<tanggal>.sqlite|.sql`, sehingga masih bisa dikembalikan
   manual bila salah memilih berkas. Nama berkas itu ditampilkan pada pesan hasil pemulihan.
5. Foto dari arsip disalin ke folder unggahan **lebih dulu**, baru database diganti dalam satu
   transaksi (SQLite) sehingga aman walau aplikasi sedang dipakai (berkas dengan nama sama
   ditimpa, berkas lain tetap dipertahankan).
6. Path foto hasil pemulihan dirapikan ke format folder unggahan. Backup versi lama menulis path
   berawalan `storage/` dan migrasi perbaikannya tidak akan terulang (tabel `migrations` ikut
   dikembalikan), sehingga tanpa langkah ini foto tidak terbaca.
7. Setelah pemulihan, sistem memeriksa setiap foto yang dirujuk database dan memberi tahu bila
   ada yang berkasnya tidak ada. Pemulihan dari berkas `.sqlite`/`.sql` memang tidak membawa
   foto — gunakan arsip `.zip` hasil **Backup Lengkap** bila foto ikut dipulihkan.
8. Di aplikasi desktop, skema database langsung dilengkapi (`php artisan migrate`) lalu server
   backend direstart, karena data dari backup versi lama belum tentu punya tabel/kolom baru.
   Tidak perlu menutup dan membuka aplikasi lagi. Halaman dimuat ulang setelah selesai dan Anda
   mungkin perlu login kembali.
9. Bila mode hybrid aktif, jalankan **Sinkron Sekarang** di tab **Hosting & Sinkronisasi** agar data
   hosting menyesuaikan hasil pemulihan.

> Pemulihan hanya dapat dilakukan **admin** dan selalu memerlukan password admin. Batas unggah pada
> aplikasi desktop sudah dinaikkan (1 GB) agar arsip backup berisi banyak foto tetap dapat dipulihkan.

### Pengingat Backup Mingguan (otomatis)

Backup bersifat rutin mingguan dan berjalan **otomatis** tanpa perlu dijadwalkan manual:

- Saat aplikasi dibuka, sistem memeriksa kapan backup terakhir dibuat (disimpan pada tabel `app_settings`).
- Bila sudah lebih dari **7 hari** — misalnya aplikasi baru dibuka hari **Senin** dan backup terakhir masih
  dari minggu lalu — muncul dialog **Pengingat Backup Mingguan** yang menawarkan pilihan:
  - **Backup Sekarang** — masukkan password admin, arsip lengkap (database + foto) langsung diunduh dan
    disimpan lewat dialog simpan berkas (di desktop) atau folder unduhan (di peramban).
  - **Nanti, Minggu Depan** — memilih tidak backup; pengingat ditunda 7 hari sehingga tidak muncul lagi
    pada siklus tersebut.
  - **Buka Pengaturan** — menutup dialog lalu membawa Anda ke **Pengaturan Sistem** untuk melakukan
    backup di sana.
- Setelah backup berhasil dibuat, waktu backup terakhir diperbarui dan siklus 7 hari dimulai ulang, jadi
  dialog tidak akan muncul lagi sampai siklus berikutnya.
- Dialog hanya muncul untuk akun **admin** (backup memang hak khusus admin) dan tidak pernah muncul bila
  backup dihitung sudah beres. Halaman **Pengaturan Sistem** menampilkan kapan backup terakhir dibuat.
- State ini tersimpan di server (`app_settings`), sehingga konsisten di semua komputer yang memakai instalasi
  yang sama.

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
   app/Models/                   User, Item, Student, Loan, LoanItem
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
   src/pages/                     Dashboard, barang, mahasiswa, peminjaman, peminjaman resmi, laporan, scan QR (kamera/manual, multi-metode),
                           detail transaksi, pengembalian, user, teknisi, profil, lupa/reset password

desktop/
    main.js                        Proses utama Electron: boot backend, PHP portable, wizard, tunnel, pembaruan
    preload.js                     Jembatan aman window.desktop
    setup.html / setup.js          Wizard konfigurasi awal (identitas aplikasi + email)
    scripts/                       prepare-php, prepare-cloudflared, prepare-build, smoke-test
    resources/                     Runtime PHP portable, cloudflared, backend & frontend hasil rakit

LICENSE.md                         Lisensi proprietary dan daftar komponen pihak ketiga
README.md                          Dokumentasi utama (deployment, API, dan operasional)
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

Seeder (`DatabaseSeeder`) membuat akun berikut jika belum ada. Login aplikasi memakai
**username**, bukan email.

| Role | Username | Email | Password awal |
| :--- | :--- | :--- | :--- |
| Admin | `admin` | `admin@pnp.local` | `password` |
| Asisten | `asisten` | `asisten@pnp.local` | `password` |

Seeder juga menambahkan satu data teknisi contoh (`NOFA HENDRAYANA.ST`, NIP
`197907182025211025`) bila belum ada.

Untuk menambahkan data peminjaman contoh (100 transaksi dummy), jalankan
`php artisan db:seed --class=LoanDummySeeder` — lihat [Menjalankan di Lokal](#menjalankan-di-lokal).

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

### Mahasiswa

| Method | Endpoint | Akses | Keterangan |
| :--- | :--- | :--- | :--- |
| GET | `/api/students` | Auth | Daftar dan pencarian data mahasiswa |
| POST | `/api/students` | Admin/Asisten | Menambahkan data mahasiswa |
| PUT | `/api/students/{student}` | Admin/Asisten | Memperbarui data mahasiswa |
| DELETE | `/api/students/{student}` | Admin/Asisten | Menghapus data mahasiswa |
| POST | `/api/students/import` | Admin/Asisten | Impor berkas CSV/XLSX/XLS (NIM kunci utama, email kunci cadangan) |
| POST | `/api/students/import/csv-url` | Admin/Asisten | Sinkronisasi sumber CSV Google Sheets terpublikasi; membalas `ok`, `imported`, `updated`, `unchanged`, `synced_at`, `errors` |
| GET | `/api/students/import/source` | Auth | URL sumber sinkronisasi tersimpan + `last_synced_at` |
| POST | `/api/students/import/source` | Admin/Asisten | Menyimpan URL sumber sinkronisasi |
| GET | `/api/students/import/template` | Admin/Asisten | Mengunduh template impor (`.xls`) |
| POST | `/api/desktop/students/import-csv-url` | Kunci desktop | Sama dengan `import/csv-url`, dipakai aplikasi desktop lewat `X-Desktop-Key` |

### Backup & Pemulihan

| Method | Endpoint | Akses | Keterangan |
| :--- | :--- | :--- | :--- |
| GET | `/api/backups/status` | Admin | Mode database aktif, ketersediaan backup SQLite/MySQL/lengkap, jumlah + ukuran berkas foto, dan `reminder` (kapan backup terakhir, apakah sudah jatuh tempo) |
| POST | `/api/backups/full` | Admin | Mengunduh arsip `.zip` berisi database + seluruh foto + `manifest.json` (butuh `password` admin) |
| POST | `/api/backups/sqlite` | Admin | Mengunduh berkas database SQLite saja (butuh `password` admin) |
| POST | `/api/backups/mysql` | Admin | Mengunduh dump `.sql` MySQL saja (butuh `password` admin) |
| POST | `/api/backups/restore` | Admin | Memulihkan database (+ foto bila arsip lengkap) dari unggahan `file`; wajib `password` admin; membalas `ok`, `database`, `photos`, `safety_backup` |
| POST | `/api/backups/reminder/snooze` | Admin | Menunda pengingat backup mingguan (tombol "Nanti"); opsional `days` (1–60, default 7) |

### Peminjaman

| Method | Endpoint | Akses | Keterangan |
| :--- | :--- | :--- | :--- |
| GET | `/api/loans` | Auth | Daftar, filter status, search, pagination |
| GET | `/api/loans/{loan}` | Auth | Detail transaksi |
| GET | `/api/loans/qr/{uuid}` | Auth | Lookup melalui UUID QR |
| GET | `/api/loans/report/download` | Auth | Mengunduh laporan peminjaman dalam format PDF; mendukung filter status/tanggal dan data penandatangan |
| GET | `/api/loans/clearance/borrowers` | Auth | Daftar mahasiswa (sumber sama dengan Data Mahasiswa) + status bebas labor, `has_loans`, dan `letter_status`; search nama/NIM/email |
| GET | `/api/loans/clearance/detail` | Auth | Rincian barang yang belum kembali dan riwayat pengembalian satu peminjam (identitas dari data mahasiswa bila terdaftar) |
| POST | `/api/loans/clearance/download` | Admin/Asisten | Menerbitkan surat PDF: surat bebas labor bila tidak ada tanggungan (termasuk peminjam yang belum pernah meminjam), surat keterangan tanggungan bila masih ada `borrowed`/`pending` |
| POST | `/api/loans/clearance/print` | Admin/Asisten | Menghasilkan dokumen HTML untuk cetak satuan / cetak masal; berlaku untuk semua peminjam bebas labor, termasuk yang belum pernah meminjam (ditolak 422 bila masih memiliki tanggungan) |

| POST | `/api/loans/official/download` | Admin/Asisten | Membuat peminjaman skala besar dan mengunduh surat resmi PDF |
| GET | `/api/loans/code/{code}` | Admin/Asisten | Lookup melalui kode peminjaman |
| POST | `/api/loans` | Admin/Asisten | Membuat transaksi dan mengurangi stok |
| POST | `/api/loans/{loan}/return` | Admin/Asisten | Memproses pengembalian |
| POST | `/api/loans/upload-pdf` | Admin/Asisten | Membaca UUID/kode dari PDF |
- `POST /api/desktop/mail-test` (khusus desktop, header `X-Desktop-Key`): mengirim email percobaan dari wizard.
- `POST /api/desktop/branding` (khusus desktop, header `X-Desktop-Key`): menyimpan nama & logo aplikasi dari wizard konfigurasi awal — dipakai sebelum admin login karena `POST /api/branding` butuh sesi admin.
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

Test yang tersedia saat ini: `LoanQrCodeMailTest` (email bukti QR + tautan unduh publik),
`DesktopBrandingTest` (endpoint branding khusus aplikasi desktop), dan
`HybridSettingsTest` (konfigurasi mode hybrid), ditambah test contoh bawaan Laravel.
Belum tersedia test integrasi khusus untuk stok, multi-item, autentikasi berbasis role,
PDF, dan alur pengembalian.

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

1. Jalankan installer `Peminjaman Barang PNP Setup <versi>.exe` dari halaman **Releases**
   (atau dari `desktop/release/` bila Anda membangunnya sendiri).
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

Cara paling praktis: gunakan **Backup Lengkap (Database + Foto)** pada **Pengaturan Sistem** →
tersimpan satu berkas `.zip` yang memuat database dan seluruh foto, dan dapat dipulihkan kembali
lewat bagian **Impor / Pulihkan Database** pada halaman yang sama (lihat
[Backup & Pemulihan Data](#backup--pemulihan-data)).

Cara manual (salin seluruh folder data):

1. Tutup aplikasi.
2. Buka File Explorer.
3. Masukkan `%APPDATA%` pada baris alamat.
4. Cari folder `Peminjaman Barang PNP`.
5. Salin folder tersebut ke flash drive, hard disk eksternal, atau penyimpanan aman.

Untuk memulihkan data secara manual, tutup aplikasi pada komputer tujuan lalu ganti folder data
aplikasinya dengan salinan backup. Jangan mengedit file SQLite secara manual.

Untuk deployment web/hosting, simpan salinan berkas backup lengkap di luar server; folder foto
(`storage/app/public`) dan database (`storage/app/backups` untuk salinan sebelum pemulihan) juga
perlu ikut terjaga.

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

Installer `.exe` dibagikan melalui **GitHub Release** (bukan di-commit ke source). Ada dua cara:

1. **Otomatis (disarankan):** naikkan `version` pada `desktop/package.json`, lalu push ke
   branch `Syaiful`, `main`, atau `master`. Workflow `.github/workflows/release-desktop.yml`
   membangun installer dan menerbitkannya sebagai Release `v{versi}`; release yang masih
   berstatus draft difinalkan otomatis pada langkah terakhir.
2. **Manual:** jalankan `npm run dist`, lalu buat Release dengan tag sesuai versi di
   `desktop/package.json` (mis. `v1.1.5`) dan upload **tiga file** dari `desktop/release/`:
   installer `.exe`, `latest.yml`, dan `.exe.blockmap`. Dua file terakhir wajib ada agar
   aplikasi versi lama dapat mendeteksi pembaruan — lihat
   [Pembaruan Otomatis (Auto-Update)](#pembaruan-otomatis-auto-update).

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
   > **Penting saat memperbarui aplikasi:** migrasi database harus dijalankan setiap kali
   > ada versi baru. Contoh gejala bila terlupa: `Unknown column 'is_primary' in 'order clause'`
   > (halaman Kelola Teknisi) — gejalanya sudah ditangani agar halaman tidak ikut gagal,
   > tetapi fitur baru (jabatan, WhatsApp, tanda tangan digital teknisi) baru aktif setelah
   > migrasi dijalankan. Di aplikasi desktop, migrasi berjalan otomatis saat aplikasi dibuka.
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

Sejak versi **1.0.3**, aplikasi desktop yang terinstal dapat mendeteksi dan memasang
pembaruan dari GitHub Releases tanpa perlu install ulang manual:

1. Aplikasi memeriksa versi baru di latar belakang — ±20 detik setelah aplikasi siap,
   lalu berulang setiap 4 jam. Bila tidak ada versi baru, tidak muncul notifikasi apa pun.
2. Bila versi baru tersedia, muncul **popup "Pembaruan Tersedia"** dengan pilihan
   **Unduh & Instal** atau **Nanti**. Pemeriksaan manual juga tersedia melalui menu
   **Aplikasi → Perbarui Aplikasi…** dan panel **Pengaturan** (tombol gear) di aplikasi.
3. Pengunduhan **tidak dilakukan otomatis**: installer baru diunduh hanya setelah
   pengguna memilih **Unduh & Instal** (atau menu **Aplikasi → Pengunduh Pembaruan…**),
   dengan indikator progres. Petugas tetap dapat bekerja selama unduhan berlangsung.
4. Setelah unduhan selesai, aplikasi menutup diri lalu memasang versi baru secara
   **senyap** (tanpa wizard NSIS) enam detik kemudian, dan terbuka kembali dengan versi
   baru. Data (`%APPDATA%\Peminjaman Barang PNP`) serta konfigurasi tidak tersentuh
   installer.
5. Status pembaruan juga tampil pada menu **Aplikasi** — mis. **Instal Pembaruan…**
   aktif setelah unduhan siap — dan pada panel **Pengaturan** (gear).
6. Log pembaruan ditulis ke `%APPDATA%\Peminjaman Barang PNP\update.log`.

**Kanal pembaruan (stabil & beta).** Tersedia dua kanal rilis:

| Kanal | Sumber | Cocok untuk |
| :--- | :--- | :--- |
| **Stabil** (default) | rilis final, mis. `1.2.3` (`latest.yml`) | seluruh petugas — versi paling teruji |
| **Beta** | rilis prarilis, mis. `1.2.4-beta.1` (`beta.yml`) | uji coba fitur baru lebih awal |

Kanal diubah lewat **Manajemen → Pengaturan Sistem** → tab **Tentang & Pembaruan** →
tombol **Stabil/Beta**, atau menu **Aplikasi → Kanal Pembaruan**. Pilihan disimpan pada
`desktop-config.json` (`updateChannel`). Aplikasi yang memakai kanal **Stabil tidak pernah**
menerima rilis beta; aplikasi yang memilih kanal **Beta** tetap menerima rilis stabil
terbaru bila belum ada beta baru.

> **Instal pertama:** versi ≤1.0.2 yang sudah terinstal belum berisi modul pembaruan,
> jadi perlu diinstal ulang **manual** ke 1.0.3 sebanyak satu kali. Setelah itu semua
> versi berikutnya dapat diperbarui dari dalam aplikasi.

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
> diset). Upload manual juga boleh: buat Release dengan tag sesuai versi di
> `desktop/package.json` (mis. `v1.1.5`) dan upload ketiga file dari `desktop/release/`.
> File `latest.yml` wajib hadir agar versi lama bisa mendeteksi pembaruan.

Rilis **beta** (uji coba lebih awal) — beri sufiks prarilis pada `version`:

```powershell
cd desktop
# contoh: version di package.json diubah menjadi 1.2.4-beta.1 lebih dulu
$env:EP_PRE_RELEASE='true'   # wajib untuk versi prarilis
npm run dist:publish
```

> Versi ber-sufiks (`1.2.4-beta.1`) harus diterbitkan sebagai **GitHub prerelease**:
> hanya aplikasi berkanal Beta yang mendeteksinya, instalasi stabil tetap aman. Cara
> paling ringkas tetap cukup **push** kenaikan `version` tersebut — workflow
> `.github/workflows/release-desktop.yml` sudah menandai rilis beta secara otomatis.

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
