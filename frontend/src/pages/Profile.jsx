import { useEffect, useState } from 'react'
import { useAuth } from '../context/AuthContext'
import api from '../api/axios'
import { UserCircle, Mail, Shield, Save } from 'lucide-react'

function Profile() {
  const { user, setUser } = useAuth()
  const [name, setName] = useState(user?.name || '')
  const [email, setEmail] = useState(user?.email || '')
  const [password, setPassword] = useState('')
  const [passwordConfirmation, setPasswordConfirmation] = useState('')
  const [saving, setSaving] = useState(false)
  const [message, setMessage] = useState('')
  const [error, setError] = useState('')

  useEffect(() => {
    setName(user?.name || '')
    setEmail(user?.email || '')
  }, [user])

  const roleLabels = {
    admin: 'Petugas Utama',
    assistant: 'Asisten Petugas',
    borrower: 'Peminjam',
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    setError('')
    setMessage('')
    setSaving(true)

    const payload = { name, email }
    if (password) {
      if (password !== passwordConfirmation) {
        setError('Konfirmasi password tidak cocok')
        setSaving(false)
        return
      }
      payload.password = password
      payload.password_confirmation = passwordConfirmation
    }

    try {
      const response = await api.put(`/users/${user.id}`, payload)
      const updatedUser = response.data.user
      const currentUser = { ...user, name: updatedUser.name, email: updatedUser.email }
      setUser(currentUser)
      localStorage.setItem('user', JSON.stringify(currentUser))
      setPassword('')
      setPasswordConfirmation('')
      setMessage('Profil berhasil diperbarui')
    } catch (err) {
      const data = err.response?.data
      if (data?.errors) {
        setError(Object.values(data.errors).flat().join(', '))
      } else {
        setError(data?.message || 'Gagal memperbarui profil')
      }
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="max-w-2xl mx-auto">
      <div className="mb-8">
        <h1 className="text-2xl font-bold text-slate-900">Profil Saya</h1>
        <p className="text-slate-500 mt-1">Kelola informasi akun Anda</p>
      </div>

      <div className="bg-white rounded-xl border border-slate-200 overflow-hidden mb-6">
        <div className="px-6 py-6 flex items-center gap-4 bg-gradient-to-r from-slate-50 to-cyan-50">
          <div className="w-20 h-20 bg-cyan-600 rounded-full flex items-center justify-center">
            <UserCircle className="w-12 h-12 text-white" />
          </div>
          <div>
            <h2 className="text-xl font-bold text-slate-900">{user?.name}</h2>
            <p className="text-sm text-slate-500 flex items-center gap-1.5 mt-1">
              <Shield className="w-4 h-4" />
              {roleLabels[user?.role] || user?.role}
            </p>
          </div>
        </div>
      </div>

      <form onSubmit={handleSubmit} className="bg-white rounded-xl border border-slate-200 p-6 space-y-5">
        {message && (
          <div className="bg-emerald-50 border border-emerald-200 text-emerald-600 text-sm rounded-lg px-4 py-3">
            {message}
          </div>
        )}

        {error && (
          <div className="bg-red-50 border border-red-200 text-red-600 text-sm rounded-lg px-4 py-3">
            {error}
          </div>
        )}

        <div>
          <label className="block text-sm font-medium text-slate-700 mb-1">Nama Lengkap</label>
          <input
            type="text"
            value={name}
            onChange={(e) => setName(e.target.value)}
            className="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 outline-none"
            required
          />
        </div>

        <div>
          <label className="block text-sm font-medium text-slate-700 mb-1">Email</label>
          <div className="relative">
            <Mail className="w-5 h-5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
            <input
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              className="w-full pl-10 pr-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 outline-none"
              required
            />
          </div>
        </div>

        <div className="border-t border-slate-200 pt-5">
          <h3 className="font-semibold text-slate-900 mb-1">Ganti Password</h3>
          <p className="text-sm text-slate-500 mb-4">
            Kosongkan jika tidak ingin mengganti password
          </p>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-slate-700 mb-1">Password Baru</label>
              <input
                type="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                className="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 outline-none"
                minLength={8}
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-slate-700 mb-1">Konfirmasi Password</label>
              <input
                type="password"
                value={passwordConfirmation}
                onChange={(e) => setPasswordConfirmation(e.target.value)}
                className="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 outline-none"
              />
            </div>
          </div>
        </div>

        <button
          type="submit"
          disabled={saving}
          className="inline-flex items-center gap-2 bg-cyan-600 hover:bg-cyan-700 text-white px-6 py-2.5 rounded-lg font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <Save className="w-5 h-5" />
          {saving ? 'Menyimpan...' : 'Simpan Perubahan'}
        </button>
      </form>
    </div>
  )
}

export default Profile