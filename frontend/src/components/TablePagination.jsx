import { ChevronLeft, ChevronRight } from 'lucide-react'

/**
 * Kontrol paginasi tabel yang dipakai bersama di semua halaman.
 *
 * Tombol "Berikutnya" baru muncul setelah data melewati 10 baris (halaman
 * kedua). Nomor baris tabel dibuat oleh pemanggil memakai `rowOffset`
 * (lihat `useTablePagination`) atau offset server-side.
 *
 * @param {object}   props
 * @param {number}   props.page        Halaman yang sedang aktif (1 = pertama).
 * @param {number}   props.lastPage    Jumlah halaman terakhir.
 * @param {Function} props.onPageChange Dipanggil dengan nomor halaman baru.
 * @param {number}   [props.total]     Total baris pada seluruh halaman.
 * @param {number}   [props.perPage]   Baris per halaman (default 10).
 * @param {string}   [props.className] Kelas tambahan untuk wadah.
 */
export default function TablePagination({
  page,
  lastPage,
  onPageChange,
  total,
  perPage = 10,
  className = '',
}) {
  if (!lastPage || lastPage <= 1) return null

  const safePage = Math.min(Math.max(1, page), lastPage)
  const firstRow = (safePage - 1) * perPage + 1
  const lastRow = Math.min(safePage * perPage, total ?? Number.MAX_SAFE_INTEGER)
  const hasPrevious = safePage > 1
  const hasNext = safePage < lastPage

  return (
    <div className={`flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between ${className}`}>
      <p className="text-sm text-slate-500">
        Menampilkan <span className="font-semibold text-slate-700">{firstRow}</span>
        {' - '}
        <span className="font-semibold text-slate-700">{lastRow}</span>
        {total != null && (
          <>
            {' dari '}
            <span className="font-semibold text-slate-700">{total}</span>
          </>
        )}{' '}
        data
      </p>

      <div className="flex items-center gap-2">
        <button
          type="button"
          onClick={() => onPageChange(safePage - 1)}
          disabled={!hasPrevious}
          className="inline-flex items-center gap-1 rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
        >
          <ChevronLeft className="h-4 w-4" />
          Sebelumnya
        </button>
        <span className="px-1 text-sm text-slate-500">
          Halaman {safePage} dari {lastPage}
        </span>
        <button
          type="button"
          onClick={() => onPageChange(safePage + 1)}
          disabled={!hasNext}
          className="inline-flex items-center gap-1 rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
        >
          Berikutnya
          <ChevronRight className="h-4 w-4" />
        </button>
      </div>
    </div>
  )
}
