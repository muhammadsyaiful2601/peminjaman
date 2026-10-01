import { useEffect, useMemo, useState } from 'react'
import api from '../api/axios'
import { useBranding } from '../context/BrandingContext'
import TablePagination from '../components/TablePagination'
import useTablePagination, { ROWS_PER_PAGE } from '../hooks/useTablePagination'
import { CalendarDays, Download, Printer, RefreshCw } from 'lucide-react'
import logoPnp from '../assets/Logo_Politeknik_Negeri_Padang_(2014).svg'
import { downloadBlob } from '../utils/downloadBlob'
import { printHtmlDocument } from '../utils/printHtml'

// Jumlah baris per halaman pada tabel laporan di layar. Saat dicetak, tabel
// tetap menampilkan seluruh transaksi hasil filter (lihat `report-print-table`).
const PER_PAGE = ROWS_PER_PAGE

const statusLabels = {
  borrowed: 'Dipinjam',
  returned: 'Dikembalikan',
  pending: 'Menunggu',
  rejected: 'Ditolak',
}

/**
 * Pesan kesalahan yang bisa dibaca dari respons gagal.
 *
 * Saat mencetak, respons bertipe teks sehingga badan kesalahan Laravel berupa
 * JSON dalam bentuk teks (bukan objek) dan harus diuraikan sendiri.
 */
function readPrintError(requestError) {
  const data = requestError?.response?.data
  if (typeof data === 'string') {
    try {
      const parsed = JSON.parse(data)
      return parsed.message || Object.values(parsed.errors || {})[0]?.[0] || 'Dokumen laporan gagal dibuat.'
    } catch {
      return 'Dokumen laporan gagal dibuat.'
    }
  }

  return data?.message || requestError?.message || 'Dokumen laporan gagal dibuat.'
}

function Reports() {
  const branding = useBranding()
  const [loans, setLoans] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [status, setStatus] = useState('')
  const [startDate, setStartDate] = useState('')
  const [endDate, setEndDate] = useState('')
  const [technicians, setTechnicians] = useState([])
  const [technicianId, setTechnicianId] = useState('')
  const [printError, setPrintError] = useState('')

  const selectedTechnician = technicians.find((technician) => String(technician.id) === String(technicianId))

  const fetchLoans = async () => {
    setLoading(true)
    setError('')
    try {
      const response = await api.get('/loans', { params: { per_page: 1000 } })
      setLoans(response.data.data || [])
    } catch (requestError) {
      setError(requestError.response?.data?.message || 'Laporan gagal dimuat.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    fetchLoans()
    api.get('/technicians').then((response) => {
      const data = response.data || []
      setTechnicians(data)
      if (data.length) setTechnicianId(String(data[0].id))
    }).catch(() => setError('Daftar teknisi gagal dimuat.'))
  }, [])

  const filteredLoans = useMemo(() => loans.filter((loan) => {
    const loanDate = loan.created_at?.slice(0, 10)
    const matchesStatus = !status || loan.status === status
    const matchesStart = !startDate || loanDate >= startDate
    const matchesEnd = !endDate || loanDate <= endDate
    return matchesStatus && matchesStart && matchesEnd
  }), [loans, status, startDate, endDate])

  // Tabel laporan di layar dibagi 10 baris per halaman. Filter tanggal/status
  // yang berubah mengembalikan tampilan ke halaman pertama.
  const {
    pageItems: reportRows,
    rowOffset: reportRowOffset,
    page: reportPage,
    lastPage: reportLastPage,
    total: reportTotal,
    goToPage: setReportPage,
  } = useTablePagination(filteredLoans, {
    perPage: PER_PAGE,
    resetKey: `${status}|${startDate}|${endDate}`,
  })

  const totalQuantity = filteredLoans.reduce((total, loan) => (
    total + (loan.loan_items?.length
      ? loan.loan_items.reduce((itemTotal, loanItem) => itemTotal + loanItem.qty, 0)
      : loan.qty)
  ), 0)

  const returnedCount = filteredLoans.filter((loan) => loan.status === 'returned').length
  const borrowedCount = filteredLoans.filter((loan) => loan.status === 'borrowed').length

  const formatDate = (date) => new Date(date).toLocaleDateString('id-ID', {
    timeZone: 'Asia/Jakarta',
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  })

  const reportPeriod = startDate || endDate
    ? `${startDate ? formatDate(`${startDate}T00:00:00`) : 'Awal'} - ${endDate ? formatDate(`${endDate}T00:00:00`) : 'Sekarang'}`
    : 'Seluruh periode'

  const handlePrint = async () => {
    setPrintError('')
    try {
      // Dokumen dicetak dari jendela cetak terpisah (bukan kerangka aplikasi),
      // sehingga sidebar & footer tetap tidak ikut tercetak dan isi laporan
      // tidak bergeser/terpotong akibat offset `md:pl-*` pada lebar kertas A4.
      const response = await api.get('/loans/report/print', {
        params: {
          status: status || undefined,
          start_date: startDate || undefined,
          end_date: endDate || undefined,
          technician_id: technicianId || undefined,
        },
        responseType: 'text',
      })
      setPrintError('')
      await printHtmlDocument(response.data)
    } catch (requestError) {
      setPrintError(readPrintError(requestError))
    }
  }

  const handleDownload = async () => {
    const response = await api.get('/loans/report/download', {
      params: {
        status: status || undefined,
        start_date: startDate || undefined,
        end_date: endDate || undefined,
        technician_id: technicianId || undefined,
      },
      responseType: 'blob',
    })
    const result = await downloadBlob(response.data, `laporan-peminjaman-${new Date().toISOString().slice(0, 10)}.pdf`)
    if (result && !result.ok && !result.canceled) throw new Error(result.message || 'File gagal disimpan.')
  }

  return (
    <div className="report-page">
      <div className="report-toolbar mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">Laporan Peminjaman</h1>
          <p className="mt-1 text-slate-500">Ringkasan transaksi peminjaman barang</p>
        </div>
        <div className="flex gap-2">
          <button
            type="button"
            onClick={fetchLoans}
            className="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50"
          >
            <RefreshCw className="h-4 w-4" />
            Muat ulang
          </button>
          <button
            type="button"
            onClick={handleDownload}
            disabled={loading || filteredLoans.length === 0}
            className="inline-flex items-center gap-2 rounded-lg border border-cyan-200 bg-white px-4 py-2.5 text-sm font-medium text-cyan-700 transition-colors hover:bg-cyan-50 disabled:cursor-not-allowed disabled:opacity-50"
          >
            <Download className="h-4 w-4" />
            Unduh laporan
          </button>
          <button
            type="button"
            onClick={handlePrint}
            disabled={loading || filteredLoans.length === 0}
            className="inline-flex items-center gap-2 rounded-lg bg-cyan-600 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-cyan-700 disabled:cursor-not-allowed disabled:opacity-50"
          >
            <Printer className="h-4 w-4" />
            Cetak laporan
          </button>
        </div>
      </div>
      {printError && <div className="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{printError}</div>}

      <div className="report-filters mb-6 grid grid-cols-1 gap-4 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-3">
        <label className="text-sm font-medium text-slate-700">
          Dari tanggal
          <div className="relative mt-1.5">
            <CalendarDays className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input type="date" value={startDate} onChange={(event) => setStartDate(event.target.value)} className="w-full rounded-lg border border-slate-300 py-2.5 pl-10 pr-3 outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500" />
          </div>
        </label>
        <label className="text-sm font-medium text-slate-700">
          Sampai tanggal
          <div className="relative mt-1.5">
            <CalendarDays className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input type="date" value={endDate} onChange={(event) => setEndDate(event.target.value)} className="w-full rounded-lg border border-slate-300 py-2.5 pl-10 pr-3 outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500" />
          </div>
        </label>
        <label className="text-sm font-medium text-slate-700">
          Status
          <select value={status} onChange={(event) => setStatus(event.target.value)} className="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500">
            <option value="">Semua status</option>
            <option value="borrowed">Dipinjam</option>
            <option value="returned">Dikembalikan</option>
            <option value="pending">Menunggu</option>
            <option value="rejected">Ditolak</option>
          </select>
        </label>
      </div>

      <div className="report-signature-fields mb-6 rounded-xl border border-slate-200 bg-white p-4">
        <label className="text-sm font-medium text-slate-700">
          Teknisi penandatangan
          <select value={technicianId} onChange={(event) => setTechnicianId(event.target.value)} className="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500">
            <option value="">Pilih teknisi</option>
            {technicians.map((technician) => <option key={technician.id} value={technician.id}>{technician.name} - NIP. {technician.nip}</option>)}
          </select>
        </label>
      </div>

      <div className="report-summary mb-6 grid grid-cols-2 gap-4 md:grid-cols-4">
        {[
          ['Total transaksi', filteredLoans.length],
          ['Total Barang', totalQuantity],
          ['Sedang dipinjam', borrowedCount],
          ['Dikembalikan', returnedCount],
        ].map(([label, value]) => (
          <div key={label} className="rounded-xl border border-slate-200 bg-white p-4">
            <p className="text-sm text-slate-500">{label}</p>
            <p className="mt-1 text-2xl font-bold text-slate-900">{value}</p>
          </div>
        ))}
      </div>

      <div className="report-content overflow-hidden rounded-xl border border-slate-200 bg-white">
        <div className="hidden print:block print-header">
          <div className="official-letterhead">
            <img src={branding.letterhead_logo_path || logoPnp} alt={branding.organization_name} className="official-logo" />
            <div className="official-identity">
              <h2>{branding.organization_ministry}</h2>
              <h3>{branding.organization_unit}</h3>
              <p>{branding.organization_name}</p>
              <p className="official-address">{branding.organization_address}</p>
              <p>{branding.organization_department}</p>
            </div>
          </div>
          <div className="official-rule" />
          <div className="official-title">
            <h1>LAPORAN PEMINJAMAN BARANG</h1>
            <p>Periode: {reportPeriod}</p>
          </div>
          <div className="official-meta">
            <span>Dicetak pada: {formatDate(new Date())}</span>
            <span>Jumlah transaksi: {filteredLoans.length}</span>
          </div>
        </div>
        {loading ? (
          <div className="p-12 text-center text-slate-500">Memuat laporan...</div>
        ) : error ? (
          <div className="p-12 text-center text-red-600">{error}</div>
        ) : filteredLoans.length === 0 ? (
          <div className="p-12 text-center text-slate-500">Tidak ada transaksi pada filter yang dipilih.</div>
        ) : (
          <>
            {/* Tabel di layar: 10 baris per halaman + tombol Berikutnya. */}
            <div className="overflow-x-auto print:hidden">
              <table className="report-table w-full text-left text-sm">
                <thead className="bg-slate-50">
                  <tr>
                    <th className="px-6 py-3 text-center font-medium text-slate-500">No.</th>
                    <th className="px-6 py-3 font-medium text-slate-500">Kode transaksi</th>
                    <th className="px-6 py-3 font-medium text-slate-500">Peminjam</th>
                    <th className="px-6 py-3 font-medium text-slate-500">Barang</th>
                    <th className="px-6 py-3 font-medium text-slate-500">Jumlah</th>
                    <th className="px-6 py-3 font-medium text-slate-500">Status</th>
                    <th className="px-6 py-3 font-medium text-slate-500">Tanggal</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {reportRows.map((loan, index) => (
                    <tr key={loan.id}>
                      <td className="px-6 py-3 text-center text-slate-500">{reportRowOffset + index + 1}</td>
                      <td className="px-6 py-3 font-mono text-xs text-slate-600">{loan.loan_code || loan.uuid?.slice(0, 8)}</td>
                      <td className="px-6 py-3">
                        <p className="font-medium text-slate-900">{loan.borrower_name}</p>
                        <p className="text-xs text-slate-500">{loan.borrower_student_id || loan.borrower_email}</p>
                      </td>
                      <td className="px-6 py-3 text-slate-600">
                        {loan.loan_items?.length ? loan.loan_items.map((loanItem) => loanItem.item?.name).join(', ') : loan.item?.name}
                      </td>
                      <td className="px-6 py-3 text-slate-600">{loan.loan_items?.length ? loan.loan_items.reduce((total, loanItem) => total + loanItem.qty, 0) : loan.qty}</td>
                      <td className="px-6 py-3 text-slate-600">{statusLabels[loan.status] || loan.status}</td>
                      <td className="px-6 py-3 text-slate-500">{formatDate(loan.created_at)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            <div className="border-t border-slate-100 px-6 py-3 print:hidden">
              <TablePagination
                page={reportPage}
                lastPage={reportLastPage}
                onPageChange={setReportPage}
                total={reportTotal}
                perPage={PER_PAGE}
              />
            </div>

            {/* Tabel khusus cetak: seluruh transaksi hasil filter, tanpa paginasi. */}
            <div className="hidden print:block">
              <table className="report-table w-full text-left text-sm">
                <thead className="bg-slate-50">
                  <tr>
                    <th className="px-6 py-3 text-center font-medium text-slate-500">No.</th>
                    <th className="px-6 py-3 font-medium text-slate-500">Kode transaksi</th>
                    <th className="px-6 py-3 font-medium text-slate-500">Peminjam</th>
                    <th className="px-6 py-3 font-medium text-slate-500">Barang</th>
                    <th className="px-6 py-3 font-medium text-slate-500">Jumlah</th>
                    <th className="px-6 py-3 font-medium text-slate-500">Status</th>
                    <th className="px-6 py-3 font-medium text-slate-500">Tanggal</th>
                  </tr>
                </thead>
                <tbody>
                  {filteredLoans.map((loan, index) => (
                    <tr key={loan.id}>
                      <td className="px-6 py-3 text-center text-slate-500">{index + 1}</td>
                      <td className="px-6 py-3 font-mono text-xs text-slate-600">{loan.loan_code || loan.uuid?.slice(0, 8)}</td>
                      <td className="px-6 py-3">
                        <p className="font-medium text-slate-900">{loan.borrower_name}</p>
                        <p className="text-xs text-slate-500">{loan.borrower_student_id || loan.borrower_email}</p>
                      </td>
                      <td className="px-6 py-3 text-slate-600">
                        {loan.loan_items?.length ? loan.loan_items.map((loanItem) => loanItem.item?.name).join(', ') : loan.item?.name}
                      </td>
                      <td className="px-6 py-3 text-slate-600">{loan.loan_items?.length ? loan.loan_items.reduce((total, loanItem) => total + loanItem.qty, 0) : loan.qty}</td>
                      <td className="px-6 py-3 text-slate-600">{statusLabels[loan.status] || loan.status}</td>
                      <td className="px-6 py-3 text-slate-500">{formatDate(loan.created_at)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </>
        )}
        <div className="hidden print:block official-signature-page">
          <div className="official-signature">
            <p>Tanah Datar, {formatDate(new Date())}</p>
            <p>Mengetahui,</p>
            <p className="signature-role">Teknisi</p>
            <div className="signature-space" />
            <p className="signature-name">{selectedTechnician?.name || '____________________________'}</p>
            <p>NIP. {selectedTechnician?.nip || '________________________'}</p>
          </div>
        </div>
      </div>
    </div>
  )
}

export default Reports




