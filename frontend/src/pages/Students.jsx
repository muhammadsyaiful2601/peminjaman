import { useEffect, useState } from 'react'
import { Mail, Pencil, Phone, Plus, Search, Trash2, UserRound } from 'lucide-react'
import api from '../api/axios'

const emptyForm = { student_id: '', name: '', email: '', phone: '' }

function Students() {
  const [students, setStudents] = useState([])
  const [form, setForm] = useState(emptyForm)
  const [search, setSearch] = useState('')
  const [editingStudent, setEditingStudent] = useState(null)
  const [loading, setLoading] = useState(true)
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')

  const fetchStudents = async (searchValue = search) => {
    setLoading(true)
    try {
      const response = await api.get('/students', { params: searchValue.trim() ? { search: searchValue.trim() } : {} })
      setStudents(response.data.data || [])
    } catch (requestError) {
      setError(requestError.response?.data?.message || 'Gagal memuat data mahasiswa.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => { fetchStudents('') }, [])

  const handleSubmit = async (event) => {
    event.preventDefault()
    setError('')
    setSuccess('')
    setSubmitting(true)
    try {
      if (editingStudent) {
        await api.put(`/students/${editingStudent.id}`, form)
        setSuccess('Data mahasiswa berhasil diperbarui.')
      } else {
        await api.post('/students', form)
        setSuccess('Data mahasiswa berhasil ditambahkan.')
      }
      setForm(emptyForm)
      setEditingStudent(null)
      fetchStudents()
    } catch (requestError) {
      const data = requestError.response?.data
      setError(data?.errors ? Object.values(data.errors).flat().join(', ') : data?.message || 'Data mahasiswa gagal disimpan.')
    } finally {
      setSubmitting(false)
    }
  }

  const handleEdit = (student) => {
    setEditingStudent(student)
    setForm({
      student_id: student.student_id,
      name: student.name,
      email: student.email,
      phone: student.phone || '',
    })
    setError('')
    setSuccess('')
  }

  const cancelEdit = () => {
    setEditingStudent(null)
    setForm(emptyForm)
  }

  const handleDelete = async (student) => {
    if (!window.confirm(`Hapus data mahasiswa "${student.name}"?`)) return
    setError('')
    try {
      await api.delete(`/students/${student.id}`)
      setSuccess('Data mahasiswa berhasil dihapus.')
      fetchStudents()
    } catch (requestError) {
      setError(requestError.response?.data?.message || 'Data mahasiswa gagal dihapus.')
    }
  }

  const updateForm = (event) => setForm((current) => ({ ...current, [event.target.name]: event.target.value }))

  return (
    <div className="mx-auto max-w-5xl">
      <div className="mb-8">
        <h1 className="text-2xl font-bold text-slate-900">Data Mahasiswa</h1>
        <p className="mt-1 text-slate-500">Simpan data mahasiswa agar pengisian peminjaman lebih cepat.</p>
      </div>

      {error && <div className="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>}
      {success && <div className="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{success}</div>}

      <form onSubmit={handleSubmit} className="mb-6 grid grid-cols-1 gap-4 rounded-xl border border-slate-200 bg-white p-6 md:grid-cols-2">
        <label className="text-sm font-medium text-slate-700">
          NIM / NIP *
          <input required name="student_id" value={form.student_id} onChange={updateForm} placeholder="Contoh: 2211082001" className="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500" />
        </label>
        <label className="text-sm font-medium text-slate-700">
          Nama lengkap *
          <input required name="name" value={form.name} onChange={updateForm} placeholder="Nama mahasiswa" className="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500" />
        </label>
        <label className="text-sm font-medium text-slate-700">
          Email *
          <input required type="email" name="email" value={form.email} onChange={updateForm} placeholder="email@kampus.ac.id" className="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500" />
        </label>
        <label className="text-sm font-medium text-slate-700">
          Nomor telepon
          <input type="tel" name="phone" value={form.phone} onChange={updateForm} placeholder="08xxxxxxxxxx" className="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500" />
        </label>
        <div className="flex gap-2 md:col-span-2">
          <button disabled={submitting} className="inline-flex items-center justify-center gap-2 rounded-lg bg-cyan-600 px-4 py-2.5 font-medium text-white hover:bg-cyan-700 disabled:opacity-50">
            {editingStudent ? <Pencil className="h-4 w-4" /> : <Plus className="h-4 w-4" />}
            {editingStudent ? 'Simpan Perubahan' : 'Tambah Mahasiswa'}
          </button>
          {editingStudent && <button type="button" onClick={cancelEdit} className="rounded-lg border border-slate-300 px-4 py-2.5 font-medium text-slate-600 hover:bg-slate-50">Batal</button>}
        </div>
      </form>

      <div className="mb-4 flex flex-col gap-2 sm:flex-row">
        <div className="relative flex-1">
          <Search className="absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
          <input value={search} onChange={(event) => setSearch(event.target.value)} onKeyDown={(event) => event.key === 'Enter' && fetchStudents()} placeholder="Cari NIM, nama, atau email..." className="w-full rounded-lg border border-slate-300 py-2.5 pl-10 pr-4 outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500" />
        </div>
        <button type="button" onClick={() => fetchStudents()} className="inline-flex items-center justify-center gap-2 rounded-lg bg-slate-800 px-5 py-2.5 font-medium text-white hover:bg-slate-900"><Search className="h-4 w-4" />Cari</button>
      </div>

      <div className="overflow-hidden rounded-xl border border-slate-200 bg-white">
        {loading ? <p className="p-8 text-center text-slate-500">Memuat data mahasiswa...</p> : students.length === 0 ? <div className="p-8 text-center text-slate-500"><UserRound className="mx-auto mb-3 h-10 w-10 text-slate-300" />Belum ada data mahasiswa.</div> : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="bg-slate-50"><tr><th className="px-5 py-3 font-medium text-slate-500">NIM / NIP</th><th className="px-5 py-3 font-medium text-slate-500">Nama</th><th className="px-5 py-3 font-medium text-slate-500">Kontak</th><th className="px-5 py-3 text-right font-medium text-slate-500">Aksi</th></tr></thead>
              <tbody className="divide-y divide-slate-200">{students.map((student) => <tr key={student.id}><td className="px-5 py-3 font-mono text-slate-700">{student.student_id}</td><td className="px-5 py-3 font-medium text-slate-900">{student.name}</td><td className="px-5 py-3 text-slate-600"><div className="flex items-center gap-1.5"><Mail className="h-3.5 w-3.5 text-slate-400" />{student.email}</div>{student.phone && <div className="mt-1 flex items-center gap-1.5 text-xs"><Phone className="h-3.5 w-3.5 text-slate-400" />{student.phone}</div>}</td><td className="px-5 py-3 text-right"><div className="inline-flex gap-2"><button type="button" onClick={() => handleEdit(student)} className="rounded-lg p-1.5 text-slate-500 hover:bg-cyan-50 hover:text-cyan-600" aria-label={`Edit ${student.name}`}><Pencil className="h-4 w-4" /></button><button type="button" onClick={() => handleDelete(student)} className="rounded-lg p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600" aria-label={`Hapus ${student.name}`}><Trash2 className="h-4 w-4" /></button></div></td></tr>)}</tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  )
}

export default Students
