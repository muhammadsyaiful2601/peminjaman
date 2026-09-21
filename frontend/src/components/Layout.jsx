import { useState, useEffect } from 'react'
import { NavLink, Outlet, useNavigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import {
  LayoutDashboard,
  Package,
  ArrowLeftRight,
  Users,
  UserCircle,
  LogOut,
  ScanLine,
  PlusCircle,
  FileText,
  FileSignature,
  ShieldCheck,
  Wrench,
  Settings,
  ChevronDown,
  ChevronLeft,
  ChevronRight,
  FolderKanban,
  LayoutList,
  Copyright as CopyrightIcon,
  Menu,
  X,
} from 'lucide-react'
import logoPnp from '../assets/Logo_Politeknik_Negeri_Padang_(2014).svg'
import { useBranding } from '../context/BrandingContext'

function Layout() {
  const { user, logout } = useAuth()
  const branding = useBranding()
  const navigate = useNavigate()
  const [sidebarOpen, setSidebarOpen] = useState(false)
  const [sidebarCollapsed, setSidebarCollapsed] = useState(false)
  const [expandedGroups, setExpandedGroups] = useState({
    operational: true,
    documents: true,
    management: true,
  })

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

  const navigationGroups = [
    {
      key: 'operational',
      label: 'Operasional',
      icon: LayoutList,
      items: [
        { to: '/', label: 'Dashboard', icon: LayoutDashboard, end: true },
        { to: '/items', label: 'Katalog Barang', icon: Package },
        { to: '/loans', label: 'Peminjaman', icon: ArrowLeftRight, end: true },
        ...((user?.role === 'admin' || user?.role === 'assistant') ? [
          { to: '/loans/new', label: 'Buat Peminjaman', icon: PlusCircle },
          { to: '/scan', label: 'Scan Pengembalian', icon: ScanLine },
        ] : []),
      ],
    },
    {
      key: 'documents',
      label: 'Dokumen',
      icon: FolderKanban,
      items: [
        ...((user?.role === 'admin' || user?.role === 'assistant') ? [
          { to: '/loans/official', label: 'Peminjaman Skala Besar', icon: FileSignature },
          { to: '/clearance', label: 'Bebas Labor', icon: ShieldCheck },
        ] : []),
        { to: '/reports', label: 'Laporan', icon: FileText },
      ],
    },
    {
      key: 'management',
      label: 'Manajemen',
      icon: Settings,
      items: (user?.role === 'admin' || user?.role === 'assistant') ? [
        { to: '/students', label: 'Data Mahasiswa', icon: UserCircle },
        ...(user?.role === 'admin' ? [
        { to: '/users', label: 'Kelola User', icon: Users },
        { to: '/technicians', label: 'Kelola Teknisi', icon: Wrench },
        { to: '/settings', label: 'Pengaturan Sistem', icon: Settings },
        ] : []),
      ] : [],
    },
  ].filter((group) => group.items.length > 0)

  const toggleGroup = (key) => {
    setExpandedGroups((current) => ({ ...current, [key]: !current[key] }))
  }

  const renderNavigation = (mobile = false) => navigationGroups.map((group) => {
    const GroupIcon = group.icon
    const isExpanded = expandedGroups[group.key]

    return (
      <div key={group.key} className="mb-4 last:mb-0">
        {!sidebarCollapsed || mobile ? (
          <button
            type="button"
            onClick={() => toggleGroup(group.key)}
            className="mb-1 flex w-full items-center justify-between px-3 py-1.5 text-[11px] font-bold uppercase tracking-wider text-slate-400 hover:text-slate-600"
          >
            <span className="flex items-center gap-2"><GroupIcon className="h-3.5 w-3.5" />{group.label}</span>
            <ChevronDown className={`h-3.5 w-3.5 transition-transform ${isExpanded ? '' : '-rotate-90'}`} />
          </button>
        ) : null}
        {(isExpanded || sidebarCollapsed) && (
          <div className="space-y-1">
            {group.items.map((item) => (
              <NavLink
                key={item.to}
                to={item.to}
                end={item.end}
                onClick={mobile ? () => setSidebarOpen(false) : undefined}
                title={sidebarCollapsed && !mobile ? item.label : undefined}
                className={({ isActive }) =>
                  `group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors ${
                    sidebarCollapsed && !mobile ? 'justify-center' : ''
                  } ${isActive ? 'bg-cyan-50 text-cyan-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'}`
                }
              >
                <item.icon className="h-5 w-5 shrink-0" />
                <span className={`overflow-hidden whitespace-nowrap transition-[max-width,opacity] duration-300 ease-in-out ${(!sidebarCollapsed || mobile) ? 'max-w-[180px] opacity-100' : 'max-w-0 opacity-0'}`}>{item.label}</span>
              </NavLink>
            ))}
          </div>
        )}
      </div>
    )
  })

  const roleLabels = {
    admin: 'Petugas Utama',
    assistant: 'Asisten Petugas',
  }

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Sidebar */}
      <aside className={`fixed inset-y-0 left-0 hidden overflow-hidden bg-white text-slate-800 transition-[width] duration-300 ease-in-out md:flex md:flex-col ${sidebarCollapsed ? 'w-20' : 'w-64'} border-r border-slate-200`}>
        <div className={`border-b border-slate-100 py-5 ${sidebarCollapsed ? 'px-3' : 'px-6'}`}>
          <h1 className={`flex items-center gap-2 text-lg font-bold text-slate-900 ${sidebarCollapsed ? 'justify-center' : ''}`}>
            <img src={branding.app_logo_path || logoPnp} alt={branding.app_name} className="h-10 w-10 object-contain" />
            <span className={`overflow-hidden whitespace-nowrap transition-[max-width,opacity] duration-300 ease-in-out ${sidebarCollapsed ? 'max-w-0 opacity-0' : 'max-w-[180px] opacity-100'}`}>{branding.app_name}</span>
          </h1>
          <p className={`mt-2 overflow-hidden whitespace-nowrap text-xs text-slate-400 transition-[max-width,opacity] duration-300 ease-in-out ${sidebarCollapsed ? 'max-w-0 opacity-0' : 'max-w-[210px] opacity-100'}`}>{branding.organization_name}</p>
        </div>

        <nav className={`min-h-0 flex-1 overflow-y-auto py-4 ${sidebarCollapsed ? 'px-2' : 'px-4'}`}>
          {renderNavigation()}
        </nav>

        <div className={`flex-shrink-0 border-t border-slate-100 py-4 ${sidebarCollapsed ? 'px-2' : 'px-4'} space-y-1`}>
          <NavLink
            to="/profile"
            title={sidebarCollapsed ? user?.name : undefined}
            className={({ isActive }) =>
              `flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors ${sidebarCollapsed ? 'justify-center' : ''} ${
                isActive
                  ? 'bg-cyan-50 text-cyan-700'
                  : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
              }`
            }
          >
            <UserCircle className="w-5 h-5" />
            <div className={`overflow-hidden whitespace-nowrap transition-[max-width,opacity] duration-300 ease-in-out ${sidebarCollapsed ? 'max-w-0 opacity-0' : 'max-w-[180px] opacity-100'}`}>
              <p className="font-medium text-slate-900">{user?.name}</p>
              <p className="text-xs text-slate-400">{roleLabels[user?.role]}</p>
            </div>
          </NavLink>
          <button
            onClick={handleLogout}
            title={sidebarCollapsed ? 'Keluar' : undefined}
            className={`flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-red-500 transition-colors hover:bg-red-50 hover:text-red-600 ${sidebarCollapsed ? 'justify-center' : ''}`}
          >
            <LogOut className="w-5 h-5" />
            <span className={`overflow-hidden whitespace-nowrap transition-[max-width,opacity] duration-300 ease-in-out ${sidebarCollapsed ? 'max-w-0 opacity-0' : 'max-w-[100px] opacity-100'}`}>Keluar</span>
          </button>
          <button
            type="button"
            onClick={() => setSidebarCollapsed((current) => !current)}
            className="mt-2 flex w-full items-center justify-center rounded-xl border border-slate-200 py-2 text-slate-500 transition-colors hover:bg-slate-50 hover:text-slate-800"
            aria-label={sidebarCollapsed ? 'Tampilkan sidebar' : 'Sembunyikan sidebar'}
            title={sidebarCollapsed ? 'Tampilkan sidebar' : 'Sembunyikan sidebar'}
          >
            {sidebarCollapsed ? <ChevronRight className="h-4 w-4" /> : <ChevronLeft className="h-4 w-4" />}
          </button>
        </div>
      </aside>

      {/* Main content */}
      <div className={`flex min-h-screen flex-col transition-[padding] duration-300 ease-in-out ${sidebarCollapsed ? 'md:pl-20' : 'md:pl-64'}`}>
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
              <img src={branding.app_logo_path || logoPnp} alt={branding.app_name} className="h-9 w-9 object-contain" />
              {branding.app_name}
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
              <img src={branding.app_logo_path || logoPnp} alt={branding.app_name} className="h-10 w-10 object-contain" />
              {branding.app_name}
            </h1>
            <button
              onClick={() => setSidebarOpen(false)}
              className="text-slate-400 hover:text-slate-600"
              aria-label="Tutup menu"
            >
              <X className="w-5 h-5" />
            </button>
          </div>

          <nav className="flex-1 space-y-1 overflow-y-auto px-4 py-4">
            {renderNavigation(true)}
          </nav>

          <div className="flex-shrink-0 px-4 py-4 border-t border-slate-100 space-y-1">
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

        <main className="flex-1 p-4 pb-16 md:p-8 md:pb-16">
          <Outlet />
        </main>

        {/* Footer */}
        <footer className={`fixed inset-x-0 bottom-0 z-20 border-t border-slate-200 bg-white px-4 py-3 transition-[left] duration-300 ease-in-out md:px-8 ${sidebarCollapsed ? 'md:left-20' : 'md:left-64'}`}>
          <p className="flex items-center justify-center gap-1.5 text-xs text-slate-400">
            <CopyrightIcon className="h-3.5 w-3.5" aria-hidden="true" />
            <span>developed by Muhammad Syaiful</span>
          </p>
        </footer>
      </div>
    </div>
  )
}

export default Layout