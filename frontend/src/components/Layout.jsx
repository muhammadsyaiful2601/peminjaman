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
} from 'lucide-react'

function Layout() {
  const { user, logout } = useAuth()
  const navigate = useNavigate()

  const handleLogout = async () => {
    await logout()
    navigate('/login')
  }

  const navItems = [
    { to: '/', label: 'Dashboard', icon: LayoutDashboard, end: true },
    { to: '/items', label: 'Katalog Barang', icon: Package },
    { to: '/loans', label: 'Peminjaman', icon: ArrowLeftRight },
  ]

  if (user?.role === 'admin' || user?.role === 'assistant') {
    navItems.push({ to: '/loans/new', label: 'Buat Peminjaman', icon: PlusCircle })
    navItems.push({ to: '/scan', label: 'Scan QR', icon: ScanLine })
  }

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
      <aside className="fixed inset-y-0 left-0 w-64 bg-slate-900 text-white hidden md:flex flex-col">
        <div className="px-6 py-6 border-b border-slate-800">
          <h1 className="text-lg font-bold text-white flex items-center gap-2">
            <QrCode className="w-6 h-6 text-cyan-400" />
            PinjamBarang
          </h1>
          <p className="text-xs text-slate-400 mt-1">Sistem Peminjaman Barang Kampus</p>
        </div>

        <nav className="flex-1 px-4 py-4 space-y-1">
          {navItems.map((item) => (
            <NavLink
              key={item.to}
              to={item.to}
              end={item.end}
              className={({ isActive }) =>
                `flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium transition-colors ${
                  isActive
                    ? 'bg-cyan-600 text-white'
                    : 'text-slate-300 hover:bg-slate-800 hover:text-white'
                }`
              }
            >
              <item.icon className="w-5 h-5" />
              {item.label}
            </NavLink>
          ))}
        </nav>

        <div className="px-4 py-4 border-t border-slate-800 space-y-3">
          <NavLink
            to="/profile"
            className={({ isActive }) =>
              `flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium transition-colors ${
                isActive
                  ? 'bg-cyan-600 text-white'
                  : 'text-slate-300 hover:bg-slate-800 hover:text-white'
              }`
            }
          >
            <UserCircle className="w-5 h-5" />
            <div>
              <p className="font-medium">{user?.name}</p>
              <p className="text-xs text-slate-400">{roleLabels[user?.role]}</p>
            </div>
          </NavLink>
          <button
            onClick={handleLogout}
            className="flex items-center gap-3 px-4 py-3 w-full rounded-lg text-sm font-medium text-red-400 hover:bg-red-950/50 hover:text-red-300 transition-colors"
          >
            <LogOut className="w-5 h-5" />
            Keluar
          </button>
        </div>
      </aside>

      {/* Main content */}
      <div className="md:pl-64 flex flex-col min-h-screen">
        {/* Mobile header */}
        <header className="md:hidden bg-slate-900 text-white px-4 py-3 flex items-center justify-between">
          <h1 className="font-bold flex items-center gap-2">
            <QrCode className="w-5 h-5 text-cyan-400" />
            PinjamBarang
          </h1>
          <button onClick={handleLogout} className="text-red-400">
            <LogOut className="w-5 h-5" />
          </button>
        </header>

        {/* Mobile nav */}
        <nav className="md:hidden bg-white border-b sticky top-0 z-10 flex overflow-x-auto">
          {navItems.map((item) => (
            <NavLink
              key={item.to}
              to={item.to}
              end={item.end}
              className={({ isActive }) =>
                `flex items-center gap-2 px-4 py-3 text-sm font-medium whitespace-nowrap border-b-2 ${
                  isActive ? 'border-cyan-600 text-cyan-600' : 'border-transparent text-gray-600'
                }`
              }
            >
              <item.icon className="w-4 h-4" />
              {item.label}
            </NavLink>
          ))}
          <NavLink
            to="/profile"
            className={({ isActive }) =>
              `flex items-center gap-2 px-4 py-3 text-sm font-medium whitespace-nowrap border-b-2 ${
                isActive ? 'border-cyan-600 text-cyan-600' : 'border-transparent text-gray-600'
              }`
            }
          >
            <UserCircle className="w-4 h-4" />
            Profil
          </NavLink>
        </nav>

        <main className="flex-1 p-4 md:p-8">
          <Outlet />
        </main>
      </div>
    </div>
  )
}

export default Layout