import { useState } from 'react'
import { Link } from 'react-router-dom'
import { ArrowLeft, Mail, Send } from 'lucide-react'
import api from '../api/axios'

function ForgotPassword() {
  const [email, setEmail] = useState('')
  const [message, setMessage] = useState('')
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)

  const handleSubmit = async (event) => {
    event.preventDefault()
    setMessage('')
    setError('')
    setLoading(true)

    try {
      const response = await api.post('/forgot-password', { email })
      setMessage(response.data.message)
    } catch (err) {
      const data = err.response?.data
      setError(data?.errors?.email?.[0] || data?.message || 'Gagal mengirim link reset password')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="min-h-screen flex items-center justify-center bg-slate-50 p-6">
      <div className="w-full max-w-md bg-white rounded-2xl shadow-sm border border-slate-200 p-8">
        <Link to="/login" className="inline-flex items-center gap-2 text-sm text-slate-500 hover:text-cyan-700 mb-8">
          <ArrowLeft className="w-4 h-4" /> Kembali ke login
        </Link>
        <h1 className="text-2xl font-bold text-slate-900">Lupa Password</h1>
        <p className="text-slate-500 text-sm mt-2 mb-6">
          Masukkan email akun Anda. Kami akan mengirimkan link untuk membuat password baru.
        </p>

        <form onSubmit={handleSubmit} className="space-y-4">
          {message && <div className="bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm rounded-lg px-4 py-3">{message}</div>}
          {error && <div className="bg-red-50 border border-red-200 text-red-600 text-sm rounded-lg px-4 py-3">{error}</div>}
          <div>
            <label className="block text-sm font-medium text-slate-700 mb-1.5">Email</label>
            <div className="relative">
              <Mail className="w-5 h-5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
              <input
                type="email"
                value={email}
                onChange={(event) => setEmail(event.target.value)}
                className="w-full pl-10 pr-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 outline-none"
                placeholder="nama@kampus.ac.id"
                required
              />
            </div>
          </div>
          <button type="submit" disabled={loading} className="w-full inline-flex justify-center items-center gap-2 bg-cyan-600 hover:bg-cyan-700 text-white font-semibold py-2.5 rounded-lg disabled:opacity-50">
            <Send className="w-4 h-4" /> {loading ? 'Mengirim...' : 'Kirim Link Reset'}
          </button>
        </form>
      </div>
    </div>
  )
}

export default ForgotPassword
