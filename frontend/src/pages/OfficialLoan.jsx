import { useEffect, useState } from 'react'
import api from '../api/axios'
import { ArrowLeft, Download, FileSignature, Plus, Trash2, Undo2 } from 'lucide-react'
import { Link } from 'react-router-dom'

function OfficialLoan() {
  const [items, setItems] = useState([])
  const [loanItems, setLoanItems] = useState([{ item_id: '', qty: 1 }])
  const [form, setForm] = useState({ borrower_name: '', borrower_email: '', borrower_nim: '', purpose: '', borrowed_date: '', return_date: '', signatory_name: '', signatory_nip: '', officer_name: '', officer_nip: '' })
  const [loading, setLoading] = useState(true)
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')
  const [successLoanId, setSuccessLoanId] = useState(null)

  useEffect(() => {
    api.get('/items?per_page=100').then((response) => setItems(response.data.data || [])).catch(() => setError('Gagal memuat daftar barang.')).finally(() => setLoading(false))
  }, [])

  const updateForm = (event) => setForm({ ...form, [event.target.name]: event.target.value })
  const updateLoanItem = (index, field, value) => setLoanItems(loanItems.map((item, itemIndex) => itemIndex === index ? { ...item, [field]: value } : item))
  const addLoanItem = () => setLoanItems([...loanItems, { item_id: '', qty: 1 }])
  const removeLoanItem = (index) => setLoanItems(loanItems.length === 1 ? loanItems : loanItems.filter((_, itemIndex) => itemIndex !== index))

  const handleSubmit = async (event) => {
    event.preventDefault()
    setError('')
    setSuccess('')
    setSubmitting(true)
    try {
      const response = await api.post('/loans/official/download', { ...form, items: loanItems }, { responseType: 'blob' })
      const url = URL.createObjectURL(response.data)
      const link = document.createElement('a')
      link.href = url
      link.download = `surat-peminjaman-resmi-${new Date().toISOString().slice(0, 10)}.pdf`
      link.click()
      URL.revokeObjectURL(url)
      setSuccessLoanId(response.headers['x-loan-id'])
      setSuccess('Transaksi resmi berhasil dibuat dan surat PDF berhasil diunduh.')
    } catch (requestError) {
      if (requestError.response?.data instanceof Blob) {
        const message = await requestError.response.data.text()
        try { setError(JSON.parse(message).message || 'Data peminjaman tidak valid.') } catch { setError('Data peminjaman tidak valid.') }
      } else {
        setError(requestError.response?.data?.message || 'Gagal membuat surat peminjaman.')
      }
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="mx-auto max-w-4xl">
      <div className="mb-6 flex items-center justify-between">
        <div><h1 className="text-2xl font-bold text-slate-900">Peminjaman Resmi</h1><p className="mt-1 text-slate-500">Buat surat untuk peminjaman skala besar</p></div>
        <Link to="/loans" className="inline-flex items-center gap-2 text-slate-500 hover:text-slate-700"><ArrowLeft className="h-4 w-4" />Kembali</Link>
      </div>
      <form onSubmit={handleSubmit} className="space-y-6">
        {error && <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-600">{error}</div>}
        {success && <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-5 text-sm text-emerald-800"><p className="font-semibold">{success}</p>{successLoanId && <Link to={`/loans/${successLoanId}`} className="mt-3 inline-flex items-center gap-2 rounded-lg bg-emerald-700 px-4 py-2.5 font-medium text-white hover:bg-emerald-800"><Undo2 className="h-4 w-4" />Proses Barang Kembali</Link>}</div>}

        <section className="rounded-xl border border-slate-200 bg-white p-6">
          <h2 className="mb-4 flex items-center gap-2 font-semibold text-slate-900"><FileSignature className="h-5 w-5 text-cyan-600" />Data surat</h2>
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
            {[
              ['borrower_name', 'Nama peminjam', 'text', 'Nama lengkap / instansi', true],
              ['borrower_email', 'Email peminjam', 'email', 'email@contoh.com', true],
              ['borrower_nim', 'NIM mahasiswa', 'text', 'Isi jika peminjam adalah mahasiswa', false],
              ['purpose', 'Keperluan peminjaman', 'text', 'Contoh: Kegiatan praktikum', true],
              ['borrowed_date', 'Tanggal mulai', 'date', '', true],
              ['return_date', 'Tanggal rencana kembali', 'date', '', true],
              ['signatory_name', 'Nama penanggung jawab', 'text', 'Nama lengkap', true],
              ['signatory_nip', 'NIP / NIM penanggung jawab', 'text', 'NIP atau NIM', true],
              ['officer_name', 'Nama petugas', 'text', 'Nama petugas yang memproses', true],
              ['officer_nip', 'NIP petugas', 'text', 'NIP petugas', true],
            ].map(([name, label, type, placeholder, required]) => <label key={name} className="text-sm font-medium text-slate-700">{label} {required ? <span className="text-red-600" aria-label="wajib diisi">*</span> : <span className="text-xs font-normal text-slate-400">(opsional)</span>}<input required={required} name={name} type={type} value={form[name]} onChange={updateForm} placeholder={placeholder} className="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500" /></label>)}
          </div>
        </section>

        <section className="rounded-xl border border-slate-200 bg-white p-6">
          <div className="mb-4 flex items-center justify-between"><h2 className="font-semibold text-slate-900">Daftar barang dan jumlah <span className="text-red-600" aria-label="wajib diisi">*</span></h2><button type="button" onClick={addLoanItem} className="inline-flex items-center gap-1 text-sm font-medium text-cyan-700"><Plus className="h-4 w-4" />Tambah barang</button></div>
          <div className="space-y-3">
            {loanItems.map((loanItem, index) => <div key={index} className="flex gap-2">
              <select required aria-label="Barang wajib dipilih" value={loanItem.item_id} onChange={(event) => updateLoanItem(index, 'item_id', event.target.value)} disabled={loading} className="min-w-0 flex-1 rounded-lg border border-slate-300 bg-white px-3 py-2.5 outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500"><option value="">Pilih barang</option>{items.map((item) => <option key={item.id} value={item.id}>{item.name} (stok: {item.stock})</option>)}</select>
              <input required aria-label="Jumlah wajib diisi" min="1" type="number" value={loanItem.qty} onChange={(event) => updateLoanItem(index, 'qty', event.target.value)} className="w-24 rounded-lg border border-slate-300 px-3 py-2.5 outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500" />
              <button type="button" onClick={() => removeLoanItem(index)} aria-label="Hapus barang" className="rounded-lg px-3 text-red-500 hover:bg-red-50"><Trash2 className="h-5 w-5" /></button>
            </div>)}
          </div>
        </section>

        <button type="submit" disabled={submitting || loading} className="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-cyan-600 px-5 py-3 font-semibold text-white transition-colors hover:bg-cyan-700 disabled:cursor-not-allowed disabled:opacity-50"><Download className="h-5 w-5" />{submitting ? 'Membuat surat...' : 'Buat & Unduh Surat Resmi'}</button>
      </form>
    </div>
  )
}

export default OfficialLoan