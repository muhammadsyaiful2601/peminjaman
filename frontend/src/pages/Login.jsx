import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import { QrCode, Mail, Lock, Eye, EyeOff } from 'lucide-react'
import hero from '../assets/hero.png'

function Login() {
  const { login } = useAuth()
  const navigate = useNavigate()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [showPassword, setShowPassword] = useState(false)
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)
  const [logoError, setLogoError] = useState(false)

  const handleSubmit = async (e) => {
    e.preventDefault()
    setError('')
    setLoading(true)
    try {
      await login(email, password)
      navigate('/')
    } catch (err) {
      setError(err.response?.data?.message || 'Login gagal. Silakan coba lagi.')
    } finally {
      setLoading(false)
    }
  }

  return (
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
                src="/logo.png"
                alt="Logo PinjamBarang"
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
            <div className="inline-flex items-center justify-center w-12 h-12 bg-cyan-600 rounded-xl">
              <QrCode className="w-6 h-6 text-white" />
            </div>
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
                <label className="block text-sm font-medium text-slate-700 mb-1.5">Email</label>
                <div className="relative">
                  <Mail className="w-5 h-5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                  <input
                    type="email"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    className="w-full pl-10 pr-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 outline-none"
                    placeholder="nama@kampus.ac.id"
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
                disabled={loading}
                className="w-full bg-cyan-600 hover:bg-cyan-700 text-white font-semibold py-2.5 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {loading ? 'Memproses...' : 'Masuk'}
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
  )
}

export default Login
