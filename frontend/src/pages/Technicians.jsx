import { useEffect, useState } from 'react'
import { Pencil, Plus, Trash2, Wrench } from 'lucide-react'
import api from '../api/axios'

function Technicians() {
  const [technicians, setTechnicians] = useState([])
  const [form, setForm] = useState({ name: '', nip: '' })
  const [editingTechnician, setEditingTechnician] = useState(null)
  const [loading, setLoading] = useState(true)
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState('')

  const fetchTechnicians = async () => {
    setLoading(true)
    try {
      const response = await api.get('/technicians')
      setTechnicians(response.data || [])
    } catch (requestError) {
      setError(requestError.response?.data?.message || 'Gagal memuat data teknisi.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => { fetchTechnicians() }, [])

  const handleSubmit = async (event) => {
    event.preventDefault()
    setError('')
    setSubmitting(true)
    try {
      if (editingTechnician) {
        await api.put(`/technicians/${editingTechnician.id}`, form)
      } else {
        await api.post('/technicians', form)
      }
      setForm({ name: '', nip: '' })
      setEditingTechnician(null)
      fetchTechnicians()
    } catch (requestError) {
      const data = requestError.response?.data
      setError(data?.errors ? Object.values(data.errors).flat().join(', ') : data?.message || 'Gagal menambahkan teknisi.')
    } finally {
      setSubmitting(false)
    }
  }

  const handleEdit = (technician) => {
    setEditingTechnician(technician)
    setForm({ name: technician.name, nip: technician.nip })
    setError('')
  }

  const cancelEdit = () => {
    setEditingTechnician(null)
    setForm({ name: '', nip: '' })
  }

  const handleDelete = async (technician) => {
    if (!window.confirm(`Hapus teknisi "${technician.name}"?`)) return
    try {
      await api.delete(`/technicians/${technician.id}`)
      fetchTechnicians()
    } catch (requestError) {
      setError(requestError.response?.data?.message || 'Gagal menghapus teknisi.')
    }
  }

  return (
    <div className="max-w-3xl">
      <div className="mb-8">
        <h1 className="text-2xl font-bold text-slate-900">Kelola Teknisi</h1>
        <p className="mt-1 text-slate-500">Nama teknisi tersedia sebagai pilihan penandatangan laporan.</p>
      </div>

      {error && <div className="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-600">{error}</div>}

      <form onSubmit={handleSubmit} className="mb-6 grid grid-cols-1 gap-4 rounded-xl border border-slate-200 bg-white p-6 md:grid-cols-[1fr_1fr_auto] md:items-end">
        <label className="text-sm font-medium text-slate-700">Nama teknisi<input required value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} placeholder="Contoh: NOFA HENDRAYANA.ST" className="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500" /></label>
        <label className="text-sm font-medium text-slate-700">NIP<input required value={form.nip} onChange={(event) => setForm({ ...form, nip: event.target.value })} placeholder="NIP teknisi" className="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500" /></label>
        <div className="flex gap-2">
          <button disabled={submitting} className="inline-flex flex-1 items-center justify-center gap-2 rounded-lg bg-cyan-600 px-4 py-2.5 font-medium text-white hover:bg-cyan-700 disabled:opacity-50">{editingTechnician ? 'Simpan Perubahan' : <><Plus className="h-4 w-4" />Tambah</>}</button>
          {editingTechnician && <button type="button" onClick={cancelEdit} className="rounded-lg border border-slate-300 px-4 py-2.5 font-medium text-slate-600 hover:bg-slate-50">Batal</button>}
        </div>
      </form>

      <div className="overflow-hidden rounded-xl border border-slate-200 bg-white">
        {loading ? <p className="p-8 text-center text-slate-500">Memuat data...</p> : technicians.length === 0 ? <div className="p-8 text-center text-slate-500"><Wrench className="mx-auto mb-3 h-10 w-10 text-slate-300" />Belum ada teknisi.</div> : <table className="w-full text-sm"><thead className="bg-slate-50"><tr><th className="px-6 py-3 text-left font-medium text-slate-500">Nama</th><th className="px-6 py-3 text-left font-medium text-slate-500">NIP</th><th className="px-6 py-3 text-right font-medium text-slate-500">Aksi</th></tr></thead><tbody className="divide-y divide-slate-200">{technicians.map((technician) => <tr key={technician.id}><td className="px-6 py-3 font-medium text-slate-900">{technician.name}</td><td className="px-6 py-3 text-slate-600">{technician.nip}</td><td className="px-6 py-3 text-right"><div className="inline-flex gap-2"><button onClick={() => handleEdit(technician)} className="rounded-lg p-1.5 text-slate-500 hover:bg-cyan-50 hover:text-cyan-600" aria-label={`Edit ${technician.name}`}><Pencil className="h-4 w-4" /></button><button onClick={() => handleDelete(technician)} className="rounded-lg p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600" aria-label={`Hapus ${technician.name}`}><Trash2 className="h-4 w-4" /></button></div></td></tr>)}</tbody></table>}
      </div>
    </div>
  )
}

export default Technicians