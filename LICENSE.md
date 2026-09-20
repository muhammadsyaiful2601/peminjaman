# Lisensi dan Hak Cipta

**Sistem Peminjaman Barang — Jurusan Teknologi Informasi, Politeknik Negeri Padang**

Copyright © 2026 **Muhammad Syaiful**. Seluruh hak dilindungi undang-undang
(*all rights reserved*).

Perangkat lunak ini **bukan open source** dan **tidak** dirilis dengan lisensi MIT.
Hak pakai hanya diberikan sesuai ketentuan pada dokumen ini atau perjanjian
tertulis terpisah.

> Dokumen ini berlaku untuk seluruh isi repositori: `backend/` (Laravel REST API),
> `frontend/` (React SPA), `desktop/` (aplikasi Electron beserta runtime PHP),
> termasuk installer, skema database, aset visual, dan dokumentasi.
> Daftar komponen pihak ketiga ada pada bagian 5.

---

## 1. Ringkasan Hak Pakai

| Pemakai | Dasar | Biaya | Jangka waktu | Cakupan |
| :--- | :--- | :--- | :--- | :--- |
| Politeknik Negeri Padang — Jurusan Teknologi Informasi | Hibah (bagian 2) | Gratis | Tanpa batas waktu | Pemakaian internal kampus, jumlah komputer tidak dibatasi |
| Institusi, perusahaan, atau perorangan lain | Lisensi komersial (bagian 3) | Sesuai perjanjian | Sesuai perjanjian | Sesuai perjanjian tertulis |

Pemakaian oleh pihak yang tidak dicakup salah satu dasar di atas **tidak
diizinkan**, termasuk pemakaian hasil salinan installer dari pihak ketiga.

## 2. Hibah untuk Politeknik Negeri Padang

Pemegang hak cipta memberikan hibah hak pakai (bukan pengalihan hak cipta) kepada
**Politeknik Negeri Padang, Jurusan Teknologi Informasi** dengan ketentuan:

1. **Gratis dan tanpa batas waktu** — tidak ada biaya lisensi, tidak ada tanggal
   berakhir, dan tidak ada kewajiban memperpanjang.
2. **Non-eksklusif** — pemegang hak cipta tetap berhak memakai, mengembangkan,
   dan melisensikan perangkat lunak ini kepada pihak lain.
3. **Non-transferable** — hak pakai tidak dapat dijual, dialihkan, atau
   diserahkan kepada institusi lain.
4. **Cakupan** — pemakaian internal kampus untuk kegiatan inventaris dan
   peminjaman barang, pada jumlah komputer petugas yang dibutuhkan.
5. **Tetap milik pemegang hak cipta** — salinan source code, dokumentasi, dan
   installer yang diterima tidak memindahkan hak cipta.
6. **Perubahan oleh kampus** — kampus boleh menyesuaikan konfigurasi, data, dan
   tampilan (nama, logo, kop surat) untuk keperluan internal. Perubahan kode
   untuk keperluan pihak lain memerlukan persetujuan tertulis.

Apabila pengembang utama sudah tidak lagi mengurus sistem ini, kampus tetap
berhak memasang ulang, memakai, dan mengoperasikan aplikasi dari salinan
installer, source code, dokumentasi, dan berkas konfigurasi yang telah diserahkan.

## 3. Lisensi Komersial untuk Institusi Lain

Pemakaian oleh institusi, perusahaan, atau perorangan di luar Politeknik Negeri
Padang hanya sah bila didasari **perjanjian lisensi tertulis** dari pemegang hak
cipta, misalnya berupa invoice dan/atau Surat Perjanjian Lisensi Perangkat Lunak.

Syarat lisensi komersial (jumlah komputer, masa berlaku, modul yang diizinkan,
dukungan, dan pemeliharaan) ditentukan pada perjanjian tersebut. Bila terjadi
perbedaan antara dokumen ini dan perjanjian tertulis, **perjanjian tertulis yang
berlaku**.

## 4. Larangan Umum

Tanpa izin tertulis dari pemegang hak cipta, dilarang:

1. Menyalin, mendistribusikan, menjual, menyewakan, atau menyebarluaskan source
   code, installer, maupun bagian mana pun dari perangkat lunak ini.
2. Mengubah, melepas, menonaktifkan, atau mengakali mekanisme lisensi, penanda
   versi, dan atribusi kepemilikan.
3. Memakai perangkat lunak ini atas nama institusi lain, atau memindahkan
   salinannya ke institusi lain.
4. Menerbitkan ulang perangkat lunak ini (baik sebagian maupun seluruhnya)
   sebagai produk sendiri, dengan atau tanpa perubahan.
5. Menghapus atau menyembunyikan pemberitahuan hak cipta, nama pengembang, dan
   daftar komponen pihak ketiga.

Pengecualian: hal-hal yang diizinkan secara tegas oleh lisensi komponen pihak
ketiga (bagian 5) atau oleh perjanjian lisensi tertulis (bagian 3).

## 5. Komponen Pihak Ketiga

Perangkat lunak ini menggunakan komponen pihak ketiga. Komponen tersebut
**tetap tunduk pada lisensinya masing-masing**, bukan pada dokumen ini:

| Komponen | Lisensi |
| :--- | :--- |
| PHP 8.4 (runtime portable di `desktop/resources/php`) | PHP License 3.01 |
| Laravel Framework 13, Laravel Sanctum, `laravel/tinker` | MIT |
| `barryvdh/laravel-dompdf` | MIT |
| `dompdf/dompdf` | LGPL-2.1 |
| `smalot/pdfparser` | LGPL-3.0 |
| `simplesoftwareio/simple-qrcode` | MIT |
| `bacon/bacon-qr-code` | BSD-2-Clause |
| React, React DOM, React Router, Axios, Tailwind CSS, Vite, Oxlint | MIT |
| `lucide-react`, `qrcode.react` | ISC |
| `html5-qrcode` | Apache-2.0 |
| Electron, `electron-updater`, `electron-builder` | MIT |
| `sharp` | Apache-2.0 |
| `png-to-ico` | MIT |
| `cloudflared` (Cloudflare Quick Tunnel) | Apache-2.0 |

Catatan kepatuhan:

- Teks lisensi komponen pihak ketiga tidak boleh dihapus. Berkas lisensi sudah
  tersedia di dalam paket (mis. `backend/vendor/*/*/LICENSE*` dan
  `desktop/resources/php/license.txt`).
- `dompdf/dompdf` dan `smalot/pdfparser` berlisensi LGPL. Pada distribusi
  installer desktop, kedua pustaka berada di folder backend yang dapat diakses
  pengguna, sehingga ketentuan LGPL mengenai penggantian pustaka tersebut tetap
  dapat dipenuhi. Jika cara distribusi diubah (mis. dibungkus ulang atau
  dipaketkan ulang), kepatuhan LGPL perlu ditinjau kembali.
- Logo Politeknik Negeri Padang (`backend/public/images/logo_kampus.png`) dan
  logo Sistem Informasi (`backend/public/images/si.png`) adalah milik
  institusi masing-masing dan dipakai untuk keperluan dokumen resmi kampus.
  Penggunaan logo tersebut di luar konteks aplikasi ini memerlukan izin
  institusi.

## 6. Data Pengguna

- Seluruh data transaksi (barang, peminjaman, foto, pengguna) disimpan pada
  infrastruktur milik pengguna: database SQLite/MySQL di komputer atau server
  institusi pengguna.
- Pengembang tidak menarik, menyalin, atau menyimpan data transaksi pengguna.
  Pengiriman email bukti dilakukan melalui akun SMTP milik institusi pengguna.
- Data adalah milik institusi pengguna dan menjadi tanggung jawab institusi
  tersebut, termasuk pencadangan (backup) dan perlindungan datanya.

## 7. Kontak

Permohonan lisensi komersial, izin pemakaian, atau pertanyaan tentang dokumen ini:

- Nama: Muhammad Syaiful
- GitHub: https://github.com/muhammadsyaiful2601

---

> **Catatan:** dokumen ini adalah pernyataan lisensi perangkat lunak, bukan
> nasihat hukum. Untuk transaksi komersial bernilai besar, sebaiknya ditinjau
> oleh tenaga hukum. Perubahan atas dokumen ini hanya sah bila dilakukan oleh
> pemegang hak cipta.
