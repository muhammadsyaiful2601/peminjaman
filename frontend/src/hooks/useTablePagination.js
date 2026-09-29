import { useEffect, useMemo, useState } from 'react'

/**
 * Jumlah baris per halaman untuk seluruh tabel/daftar di aplikasi.
 * Praktisnya: 10 baris masih muat satu layar tanpa membuat tabel
 * terlalu panjang, dan setelah 10 data tombol "Berikutnya" menjadi
 * berguna untuk petugas.
 */
export const ROWS_PER_PAGE = 10

/**
 * Paginasi sisi-klien untuk data yang sudah dimuat penuh di memori
 * (dashboard, laporan, surat bebas labor, daftar teknisi).
 *
 * Untuk halaman yang memakai paginasi server (Mahasiswa, User, Peminjaman)
 * hook ini tidak dipakai; cukup kirim nomor baris dengan
 * `rowOffset + index + 1` dan pakai komponen <TablePagination />.
 *
 * @param {Array}  rows            Data lengkap (boleh undefined/null).
 * @param {object} [options]
 * @param {number} [options.perPage]      Baris per halaman (default 10).
 * @param {*}      [options.resetKey]     Nilai yang berubah = filter/pencarian
 *                                       berubah, sehingga halaman dikembalikan ke 1.
 * @returns {{page:number,lastPage:number,total:number,perPage:number,
 *            pageItems:Array,rowOffset:number,goToPage:Function,next:Function,previous:Function}}
 */
export default function useTablePagination(rows, { perPage = ROWS_PER_PAGE, resetKey = '' } = {}) {
  const [requestedPage, setRequestedPage] = useState(1)

  const list = useMemo(() => (Array.isArray(rows) ? rows : []), [rows])
  const total = list.length
  const lastPage = Math.max(1, Math.ceil(total / perPage))

  // Halaman di-clamp saat render (bukan lewat efek) supaya tabel tidak pernah
  // sempat kosong berkedip setelah data berkurang karena penghapusan/filter.
  const page = Math.min(Math.max(1, requestedPage), lastPage)
  const rowOffset = (page - 1) * perPage

  const pageItems = useMemo(() => list.slice(rowOffset, rowOffset + perPage), [list, rowOffset, perPage])

  // Filter/pencarian berubah -> kembali ke halaman pertama.
  useEffect(() => { setRequestedPage(1) }, [resetKey])

  // Sinkronkan state dengan hasil clamp supaya tombol "Berikutnya" tetap akurat.
  useEffect(() => {
    setRequestedPage((current) => (current === page ? current : page))
  }, [page])

  return {
    page,
    lastPage,
    total,
    perPage,
    pageItems,
    // Offset baris pertama halaman ini; dipakai kolom "No." agar penomoran
    // berlanjut lintas halaman (halaman 2 mulai dari 11, 12, 13, ...).
    rowOffset,
    goToPage: setRequestedPage,
    next: () => setRequestedPage((current) => Math.min(lastPage, current + 1)),
    previous: () => setRequestedPage((current) => Math.max(1, current - 1)),
  }
}
