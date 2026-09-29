import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import api from '../api/axios'
import TablePagination from '../components/TablePagination'
import useTablePagination, { ROWS_PER_PAGE } from '../hooks/useTablePagination'
import {
  Package,
  ArrowLeftRight,
  BookOpenCheck,
  RotateCcw,
  PlusCircle,
  ScanLine,
  ChevronRight,
} from 'lucide-react'

// Jumlah baris per halaman pada tabel Transaksi terbaru. Setelah 10 data
// tombol "Berikutnya" muncul supaya petugas bisa membuka halaman berikutnya.
const PER_PAGE = ROWS_PER_PAGE
// Berapa transaksi terakhir yang diambil sekaligus, lalu dipaginasi 10 per halaman.
const RECENT_LIMIT = 100

function Dashboard() {
  const { user } = useAuth()
  const [stats, setStats] = useState({
    totalItems: 0,
    totalLoans: 0,
    borrowedLoans: 0,
    returnedLoans: 0,
  })
  const [recentLoans, setRecentLoans] = useState([])
  const [loading, setLoading] = useState(true)

  // Tabel 10 baris per halaman beserta nomor urut yang berlanjut antar halaman.
  const {
    pageItems: recentLoansPage,
    rowOffset: recentLoansOffset,
    page: recentLoansPageNumber,
    lastPage: recentLoansLastPage,
    total: recentLoansTotal,
    goToPage: goToRecentLoansPage,
  } = useTablePagination(recentLoans, { perPage: PER_PAGE })

  useEffect(() => {
    const fetchData = async () => {
      try {
        // `per_page=1` dipakai hanya untuk mengambil nilai `total` pada kartu
        // statistik, sehingga angka "Dipinjam"/"Dikembalikan" benar-benar
        // seluruh transaksi — bukan hanya 100 transaksi terbaru.
        const [itemsRes, loansRes, borrowedRes, returnedRes] = await Promise.all([
          api.get('/items?per_page=1'),
          api.get(`/loans?per_page=${RECENT_LIMIT}`),
          api.get('/loans?status=borrowed&per_page=1'),
          api.get('/loans?status=returned&per_page=1'),
        ])
        setStats({
          totalItems: itemsRes.data.total || 0,
          totalLoans: loansRes.data.total || 0,
          borrowedLoans: borrowedRes.data.total || 0,
          returnedLoans: returnedRes.data.total || 0,
        })
        setRecentLoans(loansRes.data.data || [])
      } catch (error) {
        // ignore
      } finally {
        setLoading(false)
      }
    }
    fetchData()
  }, [])

  const isStaff = user?.role === 'admin' || user?.role === 'assistant'

  const cards = [
    { label: 'Total Barang', value: stats.totalItems, icon: Package, tint: 'bg-blue-50 text-blue-600' },
    { label: 'Total Peminjaman', value: stats.totalLoans, icon: ArrowLeftRight, tint: 'bg-cyan-50 text-cyan-600' },
    { label: 'Dipinjam', value: stats.borrowedLoans, icon: BookOpenCheck, tint: 'bg-emerald-50 text-emerald-600' },
    { label: 'Dikembalikan', value: stats.returnedLoans, icon: RotateCcw, tint: 'bg-green-50 text-green-600' },
  ]

  const statusLabels = {
    borrowed: { text: 'Dipinjam', className: 'bg-emerald-50 text-emerald-700 ring-emerald-200' },
    returned: { text: 'Dikembalikan', className: 'bg-green-50 text-green-700 ring-green-200' },
  }

  const actions = []
  if (isStaff) {
    actions.push({
      to: '/loans/new',
      label: 'Buat Peminjaman',
      icon: PlusCircle,
      card: 'bg-cyan-600 hover:bg-cyan-700 text-white shadow-sm hover:shadow-md',
      iconBg: 'bg-white/20 text-white',
      textTitle: 'text-white',
      textDesc: 'text-cyan-100',
      chevron: 'text-cyan-100 group-hover:text-white',
    })
  }
  actions.push({
    to: '/items',
    label: 'Lihat Katalog',
    desc: 'Cek Ketersediaan Barang',
    icon: Package,
    card: 'bg-white border border-slate-200 shadow-sm hover:shadow-md',
    iconBg: 'bg-blue-50 text-blue-600',
    textTitle: 'text-slate-900',
    textDesc: 'text-slate-500',
    chevron: 'text-slate-300 group-hover:text-blue-600',
  })
  if (isStaff) {
    actions.push({
      to: '/scan',
      label: 'Scan QR',
      desc: 'Verifikasi Pengembalian Barang',
      icon: ScanLine,
      card: 'bg-white border border-slate-200 shadow-sm hover:shadow-md',
      iconBg: 'bg-emerald-50 text-emerald-600',
      textTitle: 'text-slate-900',
      textDesc: 'text-slate-500',
      chevron: 'text-slate-300 group-hover:text-emerald-600',
    })
  }

  return (
    <div className="dashboard-fade-in">
      <div className="mb-8">
        <h1 className="text-2xl font-bold text-slate-900">Dashboard</h1>
        <p className="text-slate-500 mt-1">
          Selamat Datang Kembali, <span className="font-semibold text-slate-900">{user?.name}</span>!
        </p>
      </div>

      {/* Statistik */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        {cards.map((card) => (
          <div
            key={card.label}
            className="bg-white rounded-2xl border border-slate-200 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all p-4"
          >
            <div className={`inline-flex items-center justify-center w-10 h-10 rounded-xl ${card.tint} mb-3`}>
              <card.icon className="w-5 h-5" />
            </div>
            <p className="text-2xl font-bold text-slate-900">{loading ? '...' : card.value}</p>
            <p className="text-sm text-slate-500">{card.label}</p>
          </div>
        ))}
      </div>

      {/* Tindakan cepat */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
        {actions.map((action) => (
          <Link
            key={action.to}
            to={action.to}
            className={`group rounded-2xl p-5 flex items-center gap-4 transition-all hover:-translate-y-0.5 ${action.card}`}
          >
            <div className={`inline-flex items-center justify-center w-12 h-12 rounded-xl shrink-0 ${action.iconBg}`}>
              <action.icon className="w-6 h-6" />
            </div>
            <div className="flex-1 min-w-0">
              <p className={`font-semibold ${action.textTitle}`}>{action.label}</p>
              <p className={`text-sm ${action.textDesc}`}>{action.desc}</p>
            </div>
            <ChevronRight className={`w-5 h-5 shrink-0 ${action.chevron} transition-colors`} />
          </Link>
        ))}
      </div>

      {/* Transaksi terbaru */}
      <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
          <h2 className="font-semibold text-slate-900">Transaksi Terbaru</h2>
          <Link to="/loans" className="text-sm text-cyan-600 font-medium hover:underline">
            Lihat Semua
          </Link>
        </div>

        {loading ? (
          <div className="p-6 text-center text-slate-500">Memuat Data...</div>
        ) : recentLoans.length === 0 ? (
          <div className="p-6 text-center text-slate-500">Belum Ada Transaksi Peminjaman.</div>
        ) : (
          <>
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="bg-slate-50 text-left">
                    <th className="w-14 px-6 py-3 text-center font-medium text-slate-500">No.</th>
                    <th className="px-6 py-3 font-medium text-slate-500">Peminjam</th>
                    <th className="px-6 py-3 font-medium text-slate-500">Barang</th>
                    <th className="px-6 py-3 font-medium text-slate-500">Jumlah</th>
                    <th className="px-6 py-3 font-medium text-slate-500">Status</th>
                    <th className="px-6 py-3 font-medium text-slate-500">Tanggal</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {recentLoansPage.map((loan, index) => {
                    const status = statusLabels[loan.status] || { text: loan.status, className: 'bg-gray-100 text-gray-700 ring-gray-200' }
                    return (
                      <tr key={loan.id} className="hover:bg-slate-50 transition-colors">
                        <td className="px-6 py-3 text-center text-slate-400">{recentLoansOffset + index + 1}</td>
                        <td className="px-6 py-3 font-medium text-slate-900">{loan.borrower_name}</td>
                        <td className="px-6 py-3 text-slate-600">{loan.item?.name}</td>
                        <td className="px-6 py-3 text-slate-600">{loan.qty}</td>
                        <td className="px-6 py-3">
                          <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ring-1 ring-inset ${status.className}`}>
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
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>

            <div className="border-t border-slate-100 px-6 py-3">
              <TablePagination
                page={recentLoansPageNumber}
                lastPage={recentLoansLastPage}
                onPageChange={goToRecentLoansPage}
                total={recentLoansTotal}
                perPage={PER_PAGE}
              />
            </div>
          </>
        )}
      </div>
    </div>
  )
}

export default Dashboard





