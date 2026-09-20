# Frontend — Sistem Peminjaman Barang PNP

Aplikasi web (SPA) React 19 + Vite untuk petugas: inventaris barang, peminjaman
multi-barang, pengembalian dengan verifikasi foto dan kondisi, scan QR, peminjaman
resmi (surat skala besar), laporan, kelola user/teknisi, profil, dan pengaturan sistem.

Antarmuka yang sama dipakai pada dua mode deployment: **website** dan **aplikasi
desktop Electron** (folder `desktop/`). Pada mode desktop, aplikasi memakai jembatan
`window.desktop` dari `preload.js` — mis. header `X-Desktop-Key`, simpan PDF,
panel pengaturan (gear), dan pembaruan aplikasi.

> Repository ini **bukan open source**. Sistem dihibahkan untuk Politeknik Negeri
> Padang dan dilisensikan secara komersial untuk institusi lain — lihat
> [`../LICENSE.md`](../LICENSE.md).

## Menjalankan

```bash
npm install
npm run dev      # http://localhost:5173 — proxy /api dan /storage ke http://localhost:8000
npm run build    # hasil build ke dist/ (dipakai juga saat merakit aplikasi desktop)
npm run lint     # oxlint
```

Backend harus berjalan di `http://localhost:8000` saat mode pengembangan
(`cd backend && php artisan serve --port=8000`); pengaturan proxy ada di `vite.config.js`.

Variabel lingkungan frontend (lihat `.env.example`):

| Variabel | Fungsi |
| :--- | :--- |
| `VITE_APP_NAME` | Nama aplikasi bawaan yang tampil sebelum branding dari server dimuat |
| `VITE_INACTIVITY_TIMEOUT_MINUTES` | Lama tidak aktif (menit) sebelum petugas keluar otomatis (bawaan 15) |

## Struktur

| Path | Isi |
| :--- | :--- |
| `src/api/axios.js` | Axios client (`baseURL` `/api`), bearer token, header `X-Desktop-Key`, dan penanganan 401 |
| `src/context/` | `AuthContext` (sesi petugas) dan `BrandingContext` (nama/logo aplikasi) |
| `src/components/` | `Layout` (navigasi + penutup), `CameraCapture` (foto peminjam/pengembalian), `HybridSettings` (panel gear: mode hybrid + pembaruan aplikasi) |
| `src/pages/` | Dashboard, `Items`, `ItemForm`, `Loans`, `NewLoan`, `LoanDetail`, `ScanQR`, `OfficialLoan`, `Reports`, `Users`, `Technicians`, `SystemSettings`, `Profile`, `Login`, `ForgotPassword`, `ResetPassword` |
| `src/hooks/` | `useIdleTimeout` (keluar otomatis saat tidak ada aktivitas) |
| `src/utils/` | `downloadBlob` (unduh/simpan PDF melalui desktop atau browser) |

## Lisensi

Frontend ini adalah bagian dari perangkat lunak **proprietary (bukan open source)** dan
tunduk pada [`../LICENSE.md`](../LICENSE.md).

Komponen pihak ketiga tetap memakai lisensinya masing-masing: React, React DOM, React
Router, Axios, Tailwind CSS, Vite, dan Oxlint (MIT); `lucide-react` dan `qrcode.react`
(ISC); serta `html5-qrcode` (Apache-2.0).
