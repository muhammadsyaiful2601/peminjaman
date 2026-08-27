import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import { QrCode, UserRound, Lock, Eye, EyeOff } from 'lucide-react'
import hero from '../assets/hero.png'
import logoPnp from '../assets/Logo_Politeknik_Negeri_Padang_(2014).svg'

function Login() {
  const { login } = useAuth()
  const navigate = useNavigate()
  const [username, setUsername] = useState('')
  const [password, setPassword] = useState('')
  const [showPassword, setShowPassword] = useState(false)
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)
  const [loginSuccess, setLoginSuccess] = useState(false)
  const [loginProgress, setLoginProgress] = useState(0)
  const [logoError, setLogoError] = useState(false)

  useEffect(() => {
    if (!loginSuccess) {
      return undefined
    }

    const startedAt = Date.now()
    const progressTimer = setInterval(() => {
      const progress = Math.min(((Date.now() - startedAt) / 3000) * 100, 100)
      setLoginProgress(progress)

      if (progress >= 100) {
        clearInterval(progressTimer)
        navigate('/')
      }
    }, 50)

    return () => clearInterval(progressTimer)
  }, [loginSuccess, navigate])

  const handleSubmit = async (e) => {
    e.preventDefault()
    setError('')
    setLoading(true)
    try {
      await login(username, password)
      setLoginSuccess(true)
    } catch (err) {
      setError(err.response?.data?.message || 'Login gagal. Silakan coba lagi.')
    } finally {
      setLoading(false)
    }
  }

  return (
    <>
      <div className="min-h-screen flex bg-slate-50">
      {/* ===== Panel Kiri: Brand / Logo ===== */}
      <div className="hidden lg:flex flex-1 items-center justify-center bg-white border-r border-slate-200 p-12">
        <div className="max-w-md w-full">
          <div className="mb-8 flex items-center gap-3">
            {logoError ? (
              <div className="inline-flex items-center justify-center w-12 h-12 bg-cyan-600 rounded-xl">
                <QrCode className="w-6 h-6 text-white" />
              </div>
            ) : (
              <img
                src={logoPnp}
                alt="Logo Politeknik Negeri Padang"
                onError={() => setLogoError(true)}
                className="w-12 h-12 object-contain"
              />
            )}
            <span className="text-xl font-bold text-slate-900">
              Pinjam<span className="text-cyan-600">Barang</span>
            </span>
          </div>

          <h1 className="text-4xl font-bold text-slate-900 leading-tight mb-4">
            Kelola peminjaman barang kampus dengan mudah.
          </h1>
          <p className="text-slate-500 leading-relaxed mb-8">
            Sistem Informasi Peminjaman Barang Kampus — kelola peminjaman,
            verifikasi foto & QR, serta pantau stok barang dalam satu tempat.
          </p>

          <div className="rounded-2xl overflow-hidden border border-slate-200 shadow-sm">
            <img src={hero} alt="Ilustrasi Sistem Peminjaman Barang" className="w-full h-64 object-cover" />
          </div>
        </div>
      </div>

      {/* ===== Panel Kanan: Form Login ===== */}
      <div className="flex-1 flex items-center justify-center p-6">
        <div className="w-full max-w-md">
          <div className="lg:hidden mb-8 flex items-center gap-3">
            <img src={logoPnp} alt="Logo Politeknik Negeri Padang" className="w-12 h-12 object-contain" />
            <span className="text-xl font-bold text-slate-900">
              Pinjam<span className="text-cyan-600">Barang</span>
            </span>
          </div>

          <div className="bg-white rounded-2xl shadow-sm border border-slate-200 p-8">
            <div className="mb-6">
              <h2 className="text-2xl font-bold text-slate-900 mb-1">Selamat Datang</h2>
              <p className="text-slate-500 text-sm">
                Silakan masuk menggunakan akun petugas Anda.
              </p>
            </div>

            <form onSubmit={handleSubmit} className="space-y-4">
              {error && (
                <div className="bg-red-50 border border-red-200 text-red-600 text-sm rounded-lg px-4 py-3">
                  {error}
                </div>
              )}

              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1.5">Username</label>
                <div className="relative">
                  <UserRound className="w-5 h-5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                  <input
                    type="text"
                    value={username}
                    onChange={(e) => setUsername(e.target.value)}
                    className="w-full pl-10 pr-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 outline-none"
                    placeholder="username Anda"
                    required
                  />
                </div>
              </div>

              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1.5">Password</label>
                <div className="relative">
                  <Lock className="w-5 h-5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                  <input
                    type={showPassword ? 'text' : 'password'}
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    className="w-full pl-10 pr-10 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 outline-none"
                    placeholder="••••••••"
                    required
                  />
                  <button
                    type="button"
                    onClick={() => setShowPassword(!showPassword)}
                    className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                  >
                    {showPassword ? <EyeOff className="w-5 h-5" /> : <Eye className="w-5 h-5" />}
                  </button>
                </div>
              </div>

              <button
                type="submit"
                disabled={loading || loginSuccess}
                className="w-full bg-cyan-600 hover:bg-cyan-700 text-white font-semibold py-2.5 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {loading ? 'Memproses...' : loginSuccess ? 'Berhasil masuk' : 'Masuk'}
              </button>
              <div className="text-center">
                <Link to="/forgot-password" className="text-sm text-cyan-700 hover:text-cyan-800 font-medium">
                  Lupa password?
                </Link>
              </div>
            </form>
          </div>
        </div>
      </div>
      </div>

      {loginSuccess && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/95 text-white">
          <div className="flex flex-col items-center gap-5 text-center">
            <img
              src={logoPnp}
              alt="Logo Politeknik Negeri Padang"
              className="h-24 w-24 object-contain animate-pulse"
            />
            <div className="flex items-center gap-3">
              <span className="h-5 w-5 rounded-full border-2 border-white/30 border-t-cyan-400 animate-spin" />
              <span className="text-sm font-medium tracking-wide text-slate-200">Menyiapkan ruang kerja...</span>
            </div>
            <div className="w-64">
              <div className="h-1.5 overflow-hidden rounded-full bg-white/20">
                <div
                  className="h-full rounded-full bg-cyan-400 transition-[width] duration-75 ease-linear"
                  style={{ width: `${loginProgress}%` }}
                />
              </div>
              <p className="mt-2 text-xs text-slate-400">{Math.round(loginProgress)}%</p>
            </div>
          </div>
        </div>
      )}
    </>
  )
}

export default Login
