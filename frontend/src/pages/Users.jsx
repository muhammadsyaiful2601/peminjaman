import { useEffect, useState } from 'react'
import api from '../api/axios'
import { Plus, Pencil, Trash2, Search, Users as UsersIcon, X } from 'lucide-react'

function Users() {
  const [users, setUsers] = useState([])
  const [loading, setLoading] = useState(true)
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [total, setTotal] = useState(0)
  const [showModal, setShowModal] = useState(false)
  const [editingUser, setEditingUser] = useState(null)
  const [form, setForm] = useState({ name: '', email: '', password: '', password_confirmation: '', role: 'assistant' })
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState('')

  const fetchUsers = async () => {
    setLoading(true)
    try {
      const params = { page, per_page: 10 }
      if (search) params.search = search
      const response = await api.get('/users', { params })
      setUsers(response.data.data || [])
      setLastPage(response.data.last_page || 1)
      setTotal(response.data.total || 0)
    } catch (err) {
      // ignore
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    fetchUsers()
  }, [page])

  useEffect(() => {
    const timer = setTimeout(() => {
      setPage(1)
      fetchUsers()
    }, 500)
    return () => clearTimeout(timer)
  }, [search])

  const roleLabels = {
    admin: 'Petugas Utama',
    assistant: 'Asisten Petugas',
  }

  const roleColors = {
    admin: 'bg-purple-100 text-purple-700',
    assistant: 'bg-cyan-100 text-cyan-700',
  }

  const openAddModal = () => {
    setEditingUser(null)
    setForm({ name: '', email: '', password: '', password_confirmation: '', role: 'assistant' })
    setError('')
    setShowModal(true)
  }

  const openEditModal = (user) => {
    setEditingUser(user)
    setForm({
      name: user.name,
      email: user.email,
      password: '',
      password_confirmation: '',
      role: user.role,
    })
    setError('')
    setShowModal(true)
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    setError('')
    setSubmitting(true)

    const payload = { ...form }
    if (!payload.password) {
      delete payload.password
      delete payload.password_confirmation
    }

    try {
      if (editingUser) {
        await api.put(`/users/${editingUser.id}`, payload)
      } else {
        await api.post('/users', payload)
      }
      setShowModal(false)
      fetchUsers()
    } catch (err) {
      const data = err.response?.data
      if (data?.errors) {
        setError(Object.values(data.errors).flat().join(', '))
      } else {
        setError(data?.message || 'Gagal menyimpan data user')
      }
    } finally {
      setSubmitting(false)
    }
  }

  const handleDelete = async (id, name) => {
    if (!window.confirm(`Yakin ingin menghapus user "${name}"?`)) return
    try {
      await api.delete(`/users/${id}`)
      fetchUsers()
    } catch (err) {
      alert(err.response?.data?.message || 'Gagal menghapus user')
    }
  }

  return (
    <div>
      <div className="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">Kelola User</h1>
          <p className="text-slate-500 mt-1">Total {total} user terdaftar</p>
        </div>
        <button
          onClick={openAddModal}
          className="inline-flex items-center gap-2 bg-cyan-600 hover:bg-cyan-700 text-white px-4 py-2.5 rounded-lg font-medium transition-colors"
        >
          <Plus className="w-5 h-5" />
          Tambah User
        </button>
      </div>

      {/* Search */}
      <div className="mb-6 max-w-md">
        <div className="relative">
          <Search className="w-5 h-5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
          <input
            type="text"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Cari nama atau email..."
            className="w-full pl-10 pr-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 outline-none"
          />
        </div>
      </div>

      {loading ? (
        <div className="text-center py-12 text-slate-500">Memuat data...</div>
      ) : users.length === 0 ? (
        <div className="text-center py-12 bg-white rounded-xl border border-slate-200">
          <UsersIcon className="w-16 h-16 text-slate-300 mx-auto mb-4" />
          <p className="text-slate-500">Tidak ada user ditemukan</p>
        </div>
      ) : (
        <>
          <div className="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="bg-slate-50">
                  <tr>
                    <th className="px-6 py-3 text-left font-medium text-slate-500">Nama</th>
                    <th className="px-6 py-3 text-left font-medium text-slate-500">Email</th>
                    <th className="px-6 py-3 text-left font-medium text-slate-500">Role</th>
                    <th className="px-6 py-3 text-right font-medium text-slate-500">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-200">
                  {users.map((user) => (
                    <tr key={user.id} className="hover:bg-slate-50">
                      <td className="px-6 py-3 font-medium text-slate-900">{user.name}</td>
                      <td className="px-6 py-3 text-slate-600">{user.email}</td>
                      <td className="px-6 py-3">
                        <span className={`inline-flex px-2 py-1 rounded-full text-xs font-medium ${roleColors[user.role] || 'bg-gray-100 text-gray-700'}`}>
                          {roleLabels[user.role] || user.role}
                        </span>
                      </td>
                      <td className="px-6 py-3 text-right">
                        <div className="inline-flex gap-2">
                          <button
                            onClick={() => openEditModal(user)}
                            className="p-1.5 text-slate-500 hover:text-cyan-600 hover:bg-cyan-50 rounded-lg"
                          >
                            <Pencil className="w-4 h-4" />
                          </button>
                          <button
                            onClick={() => handleDelete(user.id, user.name)}
                            className="p-1.5 text-slate-500 hover:text-red-600 hover:bg-red-50 rounded-lg"
                          >
                            <Trash2 className="w-4 h-4" />
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
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

      {/* Modal */}
      {showModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-2xl w-full max-w-md max-h-[90vh] overflow-y-auto">
            <div className="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
              <h2 className="font-semibold text-slate-900">
                {editingUser ? 'Edit User' : 'Tambah User'}
              </h2>
              <button onClick={() => setShowModal(false)} className="text-slate-400 hover:text-slate-600">
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleSubmit} className="p-6 space-y-4">
              {error && (
                <div className="bg-red-50 border border-red-200 text-red-600 text-sm rounded-lg px-4 py-3">
                  {error}
                </div>
              )}

              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">Nama Lengkap</label>
                <input
                  type="text"
                  value={form.name}
                  onChange={(e) => setForm({ ...form, name: e.target.value })}
                  className="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 outline-none"
                  required
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">Email</label>
                <input
                  type="email"
                  value={form.email}
                  onChange={(e) => setForm({ ...form, email: e.target.value })}
                  className="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 outline-none"
                  required
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">Role</label>
                <select
                  value={form.role}
                  onChange={(e) => setForm({ ...form, role: e.target.value })}
                  className="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 outline-none bg-white"
                  required
                >
                  <option value="assistant">Asisten Petugas</option>
                  <option value="admin">Petugas Utama</option>
                </select>
              </div>

              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">
                  Password {editingUser && '(kosongkan jika tidak diganti)'}
                </label>
                <input
                  type="password"
                  value={form.password}
                  onChange={(e) => setForm({ ...form, password: e.target.value })}
                  className="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 outline-none"
                  minLength={8}
                  required={!editingUser}
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">Konfirmasi Password</label>
                <input
                  type="password"
                  value={form.password_confirmation}
                  onChange={(e) => setForm({ ...form, password_confirmation: e.target.value })}
                  className="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 outline-none"
                  required={!editingUser}
                />
              </div>

              <div className="flex gap-3 pt-2">
                <button
                  type="button"
                  onClick={() => setShowModal(false)}
                  className="flex-1 px-4 py-2.5 border border-slate-300 text-slate-700 rounded-lg font-medium hover:bg-slate-50 transition-colors"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={submitting}
                  className="flex-1 bg-cyan-600 hover:bg-cyan-700 text-white px-4 py-2.5 rounded-lg font-medium transition-colors disabled:opacity-50"
                >
                  {submitting ? 'Menyimpan...' : 'Simpan'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  )
}

export default Users