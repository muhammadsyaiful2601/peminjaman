import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import api from '../api/axios'
import { Plus, Eye, PackageX, Search } from 'lucide-react'

function Loans() {
  const { user } = useAuth()
  const [loans, setLoans] = useState([])
  const [loading, setLoading] = useState(true)
  const [status, setStatus] = useState('')
  const [search, setSearch] = useState('')
  const [searchInput, setSearchInput] = useState('')
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [total, setTotal] = useState(0)

  const isStaff = user?.role === 'admin' || user?.role === 'assistant'

  const fetchLoans = async () => {
    setLoading(true)
    try {
      const params = { page, per_page: 10 }
      if (status) params.status = status
      if (search) params.search = search
      const response = await api.get('/loans', { params })
      setLoans(response.data.data || [])
      setLastPage(response.data.last_page || 1)
      setTotal(response.data.total || 0)
    } catch (error) {
      // ignore
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    fetchLoans()
  }, [page, status, search])

  const handleSearch = (e) => {
    e.preventDefault()
    setSearch(searchInput)
    setPage(1)
  }

  const statusLabels = {
    pending: { text: 'Menunggu', className: 'bg-amber-100 text-amber-700' },
    borrowed: { text: 'Dipinjam', className: 'bg-emerald-100 text-emerald-700' },
    returned: { text: 'Dikembalikan', className: 'bg-green-100 text-green-700' },
    rejected: { text: 'Ditolak', className: 'bg-red-100 text-red-700' },
  }

  const statusFilter = [
    { value: '', label: 'Semua' },
    { value: 'borrowed', label: 'Dipinjam' },
    { value: 'returned', label: 'Dikembalikan' },
  ]

  return (
    <div>
      <div className="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">Peminjaman</h1>
          <p className="text-slate-500 mt-1">Total {total} transaksi</p>
        </div>
        {isStaff && (
          <Link
            to="/loans/new"
            className="inline-flex items-center gap-2 bg-cyan-600 hover:bg-cyan-700 text-white px-4 py-2.5 rounded-lg font-medium transition-colors"
          >
            <Plus className="w-5 h-5" />
            Buat Peminjaman
          </Link>
        )}
      </div>

      {/* Search bar */}
      <form onSubmit={handleSearch} className="mb-4">
        <div className="relative max-w-md">
          <Search className="w-5 h-5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
          <input
            type="text"
            value={searchInput}
            onChange={(e) => setSearchInput(e.target.value)}
            className="w-full pl-10 pr-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 outline-none"
            placeholder="Cari nama, email, NIM peminjam..."
          />
        </div>
      </form>

      {/* Status filter */}
      <div className="mb-6 flex gap-2 overflow-x-auto pb-2">
        {statusFilter.map((s) => (
          <button
            key={s.value}
            onClick={() => {
              setStatus(s.value)
              setPage(1)
            }}
            className={`px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-colors ${
              status === s.value
                ? 'bg-cyan-600 text-white'
                : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50'
            }`}
          >
            {s.label}
          </button>
        ))}
      </div>

      {loading ? (
        <div className="text-center py-12 text-slate-500">Memuat data...</div>
      ) : loans.length === 0 ? (
        <div className="text-center py-12 bg-white rounded-xl border border-slate-200">
          <PackageX className="w-16 h-16 text-slate-300 mx-auto mb-4" />
          <p className="text-slate-500">Belum ada transaksi peminjaman</p>
        </div>
      ) : (
        <>
          <div className="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="bg-slate-50">
                  <tr>
                    <th className="px-6 py-3 text-left font-medium text-slate-500">Kode Transaksi</th>
                    <th className="px-6 py-3 text-left font-medium text-slate-500">Peminjam</th>
                    <th className="px-6 py-3 text-left font-medium text-slate-500">Barang</th>
                    <th className="px-6 py-3 text-left font-medium text-slate-500">Jumlah</th>
                    <th className="px-6 py-3 text-left font-medium text-slate-500">Status</th>
                    <th className="px-6 py-3 text-left font-medium text-slate-500">Tanggal</th>
                    <th className="px-6 py-3 text-right font-medium text-slate-500">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-200">
                  {loans.map((loan) => {
                    const status = statusLabels[loan.status] || {
                      text: loan.status,
                      className: 'bg-gray-100 text-gray-700',
                    }
                    return (
                      <tr key={loan.id} className="hover:bg-slate-50">
                        <td className="px-6 py-3 font-mono text-xs text-slate-500">
                          {loan.uuid.slice(0, 8)}...
                        </td>
                        <td className="px-6 py-3">
                          <p className="font-medium text-slate-900">{loan.borrower_name}</p>
                          <p className="text-xs text-slate-500">{loan.borrower_email}</p>
                        </td>
                        <td className="px-6 py-3 text-slate-600">
                          {loan.loan_items?.length
                            ? loan.loan_items.map((loanItem) => `${loanItem.item?.name} (${loanItem.qty})`).join(', ')
                            : loan.item?.name}
                        </td>
                        <td className="px-6 py-3 text-slate-600">
                          {loan.loan_items?.length
                            ? loan.loan_items.reduce((totalQty, loanItem) => totalQty + loanItem.qty, 0)
                            : loan.qty}
                        </td>
                        <td className="px-6 py-3">
                          <span
                            className={`inline-flex px-2 py-1 rounded-full text-xs font-medium ${status.className}`}
                          >
                            {status.text}
                          </span>
                        </td>
                        <td className="px-6 py-3 text-slate-500">
                          {new Date(loan.created_at).toLocaleDateString('id-ID', {
                            timeZone: 'Asia/Jakarta',
                            day: 'numeric',
                            month: 'short',
                            year: 'numeric',
                          })}
                        </td>
                        <td className="px-6 py-3 text-right">
                          <Link
                            to={`/loans/${loan.id}`}
                            className="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-cyan-50 text-cyan-600 font-medium text-xs hover:bg-cyan-100"
                          >
                            <Eye className="w-3.5 h-3.5" />
                            Detail
                          </Link>
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>
          </div>

          {lastPage > 1 && (
            <div className="mt-6 flex items-center justify-center gap-2">
              <button
                onClick={() => setPage((p) => Math.max(1, p - 1))}
                disabled={page === 1}
                className="px-4 py-2 rounded-lg border border-slate-300 text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed hover:bg-slate-50"
              >
                Sebelumnya
              </button>
              <span className="text-sm text-slate-500">
                Halaman {page} dari {lastPage}
              </span>
              <button
                onClick={() => setPage((p) => Math.min(lastPage, p + 1))}
                disabled={page === lastPage}
                className="px-4 py-2 rounded-lg border border-slate-300 text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed hover:bg-slate-50"
              >
                Berikutnya
              </button>
            </div>
          )}
        </>
      )}
    </div>
  )
}

export default Loans