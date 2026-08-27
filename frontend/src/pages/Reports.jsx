import { useEffect, useMemo, useState } from 'react'
import api from '../api/axios'
import { CalendarDays, Download, Printer, RefreshCw } from 'lucide-react'
import logoPnp from '../assets/Logo_Politeknik_Negeri_Padang_(2014).svg'

const statusLabels = {
  borrowed: 'Dipinjam',
  returned: 'Dikembalikan',
  pending: 'Menunggu',
  rejected: 'Ditolak',
}

function Reports() {
  const [loans, setLoans] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [status, setStatus] = useState('')
  const [startDate, setStartDate] = useState('')
  const [endDate, setEndDate] = useState('')
  const [signatoryName, setSignatoryName] = useState(() => localStorage.getItem('reportSignatoryName') || '')
  const [signatoryNip, setSignatoryNip] = useState(() => localStorage.getItem('reportSignatoryNip') || '')

  useEffect(() => {
    localStorage.setItem('reportSignatoryName', signatoryName)
    localStorage.setItem('reportSignatoryNip', signatoryNip)
  }, [signatoryName, signatoryNip])

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
  }, [])

  const filteredLoans = useMemo(() => loans.filter((loan) => {
    const loanDate = loan.created_at?.slice(0, 10)
    const matchesStatus = !status || loan.status === status
    const matchesStart = !startDate || loanDate >= startDate
    const matchesEnd = !endDate || loanDate <= endDate
    return matchesStatus && matchesStart && matchesEnd
  }), [loans, status, startDate, endDate])

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

  const handlePrint = () => {
    const originalTitle = document.title
    const restoreTitle = () => {
      document.title = originalTitle
      window.removeEventListener('afterprint', restoreTitle)
    }

    document.title = ''
    window.addEventListener('afterprint', restoreTitle)
    window.print()
  }

  const handleDownload = async () => {
    const response = await api.get('/loans/report/download', {
      params: {
        status: status || undefined,
        start_date: startDate || undefined,
        end_date: endDate || undefined,
        signatory_name: signatoryName || undefined,
        signatory_nip: signatoryNip || undefined,
      },
      responseType: 'blob',
    })
    const url = URL.createObjectURL(response.data)
    const link = document.createElement('a')
    link.href = url
    link.download = `laporan-peminjaman-${new Date().toISOString().slice(0, 10)}.pdf`
    link.click()
    URL.revokeObjectURL(url)
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

      <div className="report-signature-fields mb-6 grid grid-cols-1 gap-4 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-2">
        <label className="text-sm font-medium text-slate-700">
          Nama penandatangan
          <input type="text" value={signatoryName} onChange={(event) => setSignatoryName(event.target.value)} placeholder="Masukkan nama lengkap" className="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500" />
        </label>
        <label className="text-sm font-medium text-slate-700">
          NIP
          <input type="text" value={signatoryNip} onChange={(event) => setSignatoryNip(event.target.value)} placeholder="Masukkan NIP" className="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500" />
        </label>
      </div>

      <div className="report-summary mb-6 grid grid-cols-2 gap-4 md:grid-cols-4">
        {[
          ['Total transaksi', filteredLoans.length],
          ['Total barang', totalQuantity],
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
            <img src={logoPnp} alt="Logo Politeknik Negeri Padang" className="official-logo" />
            <div className="official-identity">
              <h2>POLITEKNIK NEGERI PADANG</h2>
              <h3>JURUSAN TEKNOLOGI INFORMASI</h3>
              <p>PROGRAM STUDI SISTEM INFORMASI</p>
              <p className="official-address">Kampus Politeknik Negeri Padang, Tanah Datar</p>
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
          <div className="overflow-x-auto">
            <table className="report-table w-full text-left text-sm">
              <thead className="bg-slate-50">
                <tr>
                  <th className="px-6 py-3 font-medium text-slate-500">No.</th>
                  <th className="px-6 py-3 font-medium text-slate-500">Kode transaksi</th>
                  <th className="px-6 py-3 font-medium text-slate-500">Peminjam</th>
                  <th className="px-6 py-3 font-medium text-slate-500">Barang</th>
                  <th className="px-6 py-3 font-medium text-slate-500">Jumlah</th>
                  <th className="px-6 py-3 font-medium text-slate-500">Status</th>
                  <th className="px-6 py-3 font-medium text-slate-500">Tanggal</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {filteredLoans.map((loan, index) => (
                  <tr key={loan.id}>
                    <td className="px-6 py-3 text-slate-500">{index + 1}</td>
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
        )}
        <div className="hidden print:block official-signature">
          <p>Padang, {formatDate(new Date())}</p>
          <p>Mengetahui,</p>
          <p className="signature-role">Ketua Program Studi Sistem Informasi</p>
          <div className="signature-space" />
          <p className="signature-name">{signatoryName || '____________________________'}</p>
          <p>NIP. {signatoryNip || '________________________'}</p>
        </div>
      </div>
    </div>
  )
}

export default Reports