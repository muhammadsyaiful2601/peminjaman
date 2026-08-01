import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import api from '../api/axios'
import {
  Package,
  ArrowLeftRight,
  Clock,
  CheckCircle,
  XCircle,
  QrCode,
  PlusCircle,
  ScanLine,
} from 'lucide-react'

function Dashboard() {
  const { user } = useAuth()
  const [stats, setStats] = useState({
    totalItems: 0,
    totalLoans: 0,
    pendingLoans: 0,
    borrowedLoans: 0,
    returnedLoans: 0,
    rejectedLoans: 0,
  })
  const [recentLoans, setRecentLoans] = useState([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    const fetchData = async () => {
      try {
        const [itemsRes, loansRes] = await Promise.all([
          api.get('/items?per_page=1'),
          api.get('/loans?per_page=5'),
        ])
        const loans = loansRes.data.data || []
        setStats({
          totalItems: itemsRes.data.total || 0,
          totalLoans: loansRes.data.total || 0,
          pendingLoans: loans.filter((l) => l.status === 'pending').length,
          borrowedLoans: loans.filter((l) => l.status === 'borrowed').length,
          returnedLoans: loans.filter((l) => l.status === 'returned').length,
          rejectedLoans: loans.filter((l) => l.status === 'rejected').length,
        })
        setRecentLoans(loans)
      } catch (error) {
        // ignore
      } finally {
        setLoading(false)
      }
    }
    fetchData()
  }, [])

  const isStaff = user?.role === 'admin' || user?.role === 'assistant'
  const isAdmin = user?.role === 'admin'

  const cards = [
    { label: 'Total Barang', value: stats.totalItems, icon: Package, color: 'bg-blue-500' },
    { label: 'Total Peminjaman', value: stats.totalLoans, icon: ArrowLeftRight, color: 'bg-cyan-500' },
    { label: 'Pending', value: stats.pendingLoans, icon: Clock, color: 'bg-amber-500' },
    { label: 'Dipinjam', value: stats.borrowedLoans, icon: CheckCircle, color: 'bg-emerald-500' },
    { label: 'Dikembalikan', value: stats.returnedLoans, icon: CheckCircle, color: 'bg-green-600' },
    { label: 'Ditolak', value: stats.rejectedLoans, icon: XCircle, color: 'bg-red-500' },
  ]

  const statusLabels = {
    pending: { text: 'Menunggu', className: 'bg-amber-100 text-amber-700' },
    borrowed: { text: 'Dipinjam', className: 'bg-emerald-100 text-emerald-700' },
    returned: { text: 'Dikembalikan', className: 'bg-green-100 text-green-700' },
    rejected: { text: 'Ditolak', className: 'bg-red-100 text-red-700' },
  }

  return (
    <div>
      <div className="mb-8">
        <h1 className="text-2xl font-bold text-slate-900">Dashboard</h1>
        <p className="text-slate-500 mt-1">
          Selamat datang, <span className="font-medium text-slate-700">{user?.name}</span>!
        </p>
      </div>

      {/* Quick actions */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
        {isStaff && (
          <Link
            to="/loans/new"
            className="bg-cyan-600 hover:bg-cyan-700 text-white rounded-xl p-5 flex items-center gap-4 transition-colors"
          >
            <PlusCircle className="w-8 h-8" />
            <div>
              <p className="font-semibold">Buat Peminjaman</p>
              <p className="text-sm text-cyan-100">Daftarkan peminjaman mahasiswa</p>
            </div>
          </Link>
        )}

        <Link
          to="/items"
          className="bg-white hover:bg-slate-50 border border-slate-200 rounded-xl p-5 flex items-center gap-4 transition-colors"
        >
          <Package className="w-8 h-8 text-blue-500" />
          <div>
            <p className="font-semibold text-slate-900">Lihat Katalog</p>
            <p className="text-sm text-slate-500">Cek ketersediaan barang</p>
          </div>
        </Link>

        {isStaff && (
          <Link
            to="/scan"
            className="bg-white hover:bg-slate-50 border border-slate-200 rounded-xl p-5 flex items-center gap-4 transition-colors"
          >
            <ScanLine className="w-8 h-8 text-emerald-500" />
            <div>
              <p className="font-semibold text-slate-900">Scan QR</p>
              <p className="text-sm text-slate-500">Verifikasi peminjaman</p>
            </div>
          </Link>
        )}
      </div>

      {/* Stats */}
      <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
        {cards.map((card) => (
          <div key={card.label} className="bg-white rounded-xl border border-slate-200 p-4">
            <div className={`inline-flex items-center justify-center w-10 h-10 rounded-lg ${card.color} mb-3`}>
              <card.icon className="w-5 h-5 text-white" />
            </div>
            <p className="text-2xl font-bold text-slate-900">{loading ? '...' : card.value}</p>
            <p className="text-sm text-slate-500">{card.label}</p>
          </div>
        ))}
      </div>

      {/* Recent loans */}
      <div className="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div className="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
          <h2 className="font-semibold text-slate-900">Transaksi Terbaru</h2>
          <Link to="/loans" className="text-sm text-cyan-600 font-medium hover:underline">
            Lihat semua
          </Link>
        </div>

        {loading ? (
          <div className="p-6 text-center text-slate-500">Memuat data...</div>
        ) : recentLoans.length === 0 ? (
          <div className="p-6 text-center text-slate-500">
            Belum ada transaksi peminjaman.
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-slate-50">
                <tr>
                  <th className="px-6 py-3 text-left font-medium text-slate-500">Peminjam</th>
                  <th className="px-6 py-3 text-left font-medium text-slate-500">Barang</th>
                  <th className="px-6 py-3 text-left font-medium text-slate-500">Jumlah</th>
                  <th className="px-6 py-3 text-left font-medium text-slate-500">Status</th>
                  <th className="px-6 py-3 text-left font-medium text-slate-500">Tanggal</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-200">
                {recentLoans.map((loan) => {
                  const status = statusLabels[loan.status] || { text: loan.status, className: 'bg-gray-100 text-gray-700' }
                  return (
                    <tr key={loan.id} className="hover:bg-slate-50">
                      <td className="px-6 py-3 font-medium text-slate-900">{loan.borrower_name}</td>
                      <td className="px-6 py-3 text-slate-600">{loan.item?.name}</td>
                      <td className="px-6 py-3 text-slate-600">{loan.qty}</td>
                      <td className="px-6 py-3">
                        <span className={`inline-flex px-2 py-1 rounded-full text-xs font-medium ${status.className}`}>
                          {status.text}
                        </span>
                      </td>
                      <td className="px-6 py-3 text-slate-500">
                        {new Date(loan.created_at).toLocaleDateString('id-ID', {
                          day: 'numeric',
                          month: 'short',
                          year: 'numeric',
                        })}
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  )
}

export default Dashboard