import { useState, useEffect } from 'react'
import { NavLink, Outlet, useNavigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import {
  LayoutDashboard,
  Package,
  ArrowLeftRight,
  QrCode,
  Users,
  UserCircle,
  LogOut,
  ScanLine,
  PlusCircle,
  FileText,
  FileSignature,
  Menu,
  X,
} from 'lucide-react'

function Layout() {
  const { user, logout } = useAuth()
  const navigate = useNavigate()
  const [sidebarOpen, setSidebarOpen] = useState(false)

  // Kunci scroll body saat drawer mobile terbuka
  useEffect(() => {
    document.body.style.overflow = sidebarOpen ? 'hidden' : ''
    return () => {
      document.body.style.overflow = ''
    }
  }, [sidebarOpen])

  const handleLogout = async () => {
    await logout()
    navigate('/login')
  }

  const navItems = [
    { to: '/', label: 'Dashboard', icon: LayoutDashboard, end: true },
    { to: '/items', label: 'Katalog Barang', icon: Package },
    { to: '/loans', label: 'Peminjaman', icon: ArrowLeftRight, end: true },
  ]

  if (user?.role === 'admin' || user?.role === 'assistant') {
    navItems.push({ to: '/loans/new', label: 'Buat Peminjaman', icon: PlusCircle })
    navItems.push({ to: '/scan', label: 'Scan Pengembalian', icon: ScanLine })
    navItems.push({ to: '/loans/official', label: 'Peminjaman Skala Besar', icon: FileSignature })
  }

    navItems.push({ to: '/reports', label: 'Laporan', icon: FileText })

  if (user?.role === 'admin') {
    navItems.push({ to: '/users', label: 'Kelola User', icon: Users })
  }

  const roleLabels = {
    admin: 'Petugas Utama',
    assistant: 'Asisten Petugas',
  }

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Sidebar */}
      <aside className="fixed inset-y-0 left-0 w-64 bg-white border-r border-slate-200 text-slate-800 hidden md:flex flex-col">
        <div className="px-6 py-6 border-b border-slate-100">
          <h1 className="text-lg font-bold text-slate-900 flex items-center gap-2">
            <span className="inline-flex items-center justify-center w-9 h-9 bg-cyan-600 rounded-lg">
              <QrCode className="w-5 h-5 text-white" />
            </span>
            PinjamBarang
          </h1>
          <p className="text-xs text-slate-400 mt-2">Sistem Peminjaman Barang Kampus</p>
        </div>

        <nav className="flex-1 px-4 py-4 space-y-1">
          {navItems.map((item) => (
            <NavLink
              key={item.to}
              to={item.to}
              end={item.end}
              className={({ isActive }) =>
                `flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-colors ${
                  isActive
                    ? 'bg-cyan-50 text-cyan-700'
                    : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
                }`
              }
            >
              <item.icon className="w-5 h-5" />
              {item.label}
            </NavLink>
          ))}
        </nav>

        <div className="px-4 py-4 border-t border-slate-100 space-y-1">
          <NavLink
            to="/profile"
            className={({ isActive }) =>
              `flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-colors ${
                isActive
                  ? 'bg-cyan-50 text-cyan-700'
                  : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
              }`
            }
          >
            <UserCircle className="w-5 h-5" />
            <div>
              <p className="font-medium text-slate-900">{user?.name}</p>
              <p className="text-xs text-slate-400">{roleLabels[user?.role]}</p>
            </div>
          </NavLink>
          <button
            onClick={handleLogout}
            className="flex items-center gap-3 px-4 py-3 w-full rounded-xl text-sm font-medium text-red-500 hover:bg-red-50 hover:text-red-600 transition-colors"
          >
            <LogOut className="w-5 h-5" />
            Keluar
          </button>
        </div>
      </aside>

      {/* Main content */}
      <div className="md:pl-64 flex flex-col min-h-screen">
        {/* Mobile header */}
        <header className="md:hidden bg-white border-b border-slate-200 text-slate-900 px-4 py-3 flex items-center justify-between sticky top-0 z-30">
          <div className="flex items-center gap-3">
            <button
              onClick={() => setSidebarOpen(true)}
              className="text-slate-600 hover:text-slate-900"
              aria-label="Buka menu"
            >
              <Menu className="w-6 h-6" />
            </button>
            <h1 className="font-bold flex items-center gap-2">
              <span className="inline-flex items-center justify-center w-8 h-8 bg-cyan-600 rounded-lg">
                <QrCode className="w-4 h-4 text-white" />
              </span>
              PinjamBarang
            </h1>
          </div>
          <button onClick={handleLogout} className="text-red-500">
            <LogOut className="w-5 h-5" />
          </button>
        </header>

        {/* Mobile overlay */}
        {sidebarOpen && (
          <div
            className="fixed inset-0 bg-slate-900/50 z-40 md:hidden"
            onClick={() => setSidebarOpen(false)}
          />
        )}

        {/* Mobile drawer */}
        <aside
          className={`fixed inset-y-0 left-0 w-64 bg-white text-slate-800 flex flex-col z-50 md:hidden transform transition-transform duration-300 ${
            sidebarOpen ? 'translate-x-0' : '-translate-x-full'
          }`}
        >
          <div className="px-6 py-6 border-b border-slate-100 flex items-center justify-between">
            <h1 className="text-lg font-bold text-slate-900 flex items-center gap-2">
              <span className="inline-flex items-center justify-center w-9 h-9 bg-cyan-600 rounded-lg">
                <QrCode className="w-5 h-5 text-white" />
              </span>
              PinjamBarang
            </h1>
            <button
              onClick={() => setSidebarOpen(false)}
              className="text-slate-400 hover:text-slate-600"
              aria-label="Tutup menu"
            >
              <X className="w-5 h-5" />
            </button>
          </div>

          <nav className="flex-1 px-4 py-4 space-y-1 overflow-y-auto">
            {navItems.map((item) => (
              <NavLink
                key={item.to}
                to={item.to}
                end={item.end}
                onClick={() => setSidebarOpen(false)}
                className={({ isActive }) =>
                  `flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-colors ${
                    isActive
                      ? 'bg-cyan-50 text-cyan-700'
                      : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
                  }`
                }
              >
                <item.icon className="w-5 h-5" />
                {item.label}
              </NavLink>
            ))}
          </nav>

          <div className="px-4 py-4 border-t border-slate-100 space-y-1">
            <NavLink
              to="/profile"
              onClick={() => setSidebarOpen(false)}
              className={({ isActive }) =>
                `flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-colors ${
                  isActive
                    ? 'bg-cyan-50 text-cyan-700'
                    : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
                }`
              }
            >
              <UserCircle className="w-5 h-5" />
              <div>
                <p className="font-medium text-slate-900">{user?.name}</p>
                <p className="text-xs text-slate-400">{roleLabels[user?.role]}</p>
              </div>
            </NavLink>
            <button
              onClick={handleLogout}
              className="flex items-center gap-3 px-4 py-3 w-full rounded-xl text-sm font-medium text-red-500 hover:bg-red-50 hover:text-red-600 transition-colors"
            >
              <LogOut className="w-5 h-5" />
              Keluar
            </button>
          </div>
        </aside>

        <main className="flex-1 p-4 md:p-8">
          <Outlet />
        </main>
      </div>
    </div>
  )
}

export default Layout