import { useEffect, useState } from 'react'
import { BadgeCheck, Info, PenLine, Pencil, Plus, Trash2, Upload, UserRound, X } from 'lucide-react'
import api from '../api/axios'
import TablePagination from '../components/TablePagination'
import useTablePagination, { ROWS_PER_PAGE } from '../hooks/useTablePagination'

// Jumlah kartu teknisi per halaman. Setelah 10 teknisi muncul tombol
// "Berikutnya" supaya petugas bisa membuka halaman berikutnya.
const PER_PAGE = ROWS_PER_PAGE

// Bentuk form yang dipakai untuk tambah maupun ubah teknisi.
const emptyForm = {
  name: '',
  position: '',
  nip: '',
  whatsapp: '',
  is_primary: false,
  signature_path: '',
}

/** Inisial untuk avatar kartu profil: dua huruf pertama nama. */
function initialsOf(name) {
  const parts = String(name || '').replace(/[.,]/g, ' ').trim().split(/\s+/).filter(Boolean)

  if (parts.length === 0) return '?'
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase()

  return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
}

// Gaya input dipakai berulang di form agar form tetap rata dan konsisten.
const inputClass = 'mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 outline-none transition focus:border-cyan-500 focus:ring-2 focus:ring-cyan-100'
const labelClass = 'block text-sm font-medium text-slate-700'

function Technicians() {
  const [technicians, setTechnicians] = useState([])
  const [form, setForm] = useState(emptyForm)
  const [editingTechnician, setEditingTechnician] = useState(null)
  // Tanda tangan baru dipilih di browser ( belum tersimpan di server ).
  const [signatureFile, setSignatureFile] = useState(null)
  const [signaturePreview, setSignaturePreview] = useState('')
  // Tanda tangan lama sengaja dibuang saat menyimpan.
  const [removeSignature, setRemoveSignature] = useState(false)
  const [loading, setLoading] = useState(true)
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')

  const fetchTechnicians = async () => {
    setLoading(true)
    try {
      const response = await api.get('/technicians')
      setTechnicians(response.data || [])
    } catch (requestError) {
      setError(requestError.response?.data?.message || 'Gagal Memuat Data teknisi.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => { fetchTechnicians() }, [])

  // Kartu teknisi dipaginasi 10 per halaman dengan nomor urut yang
  // berlanjut antar halaman.
  const technicianList = useTablePagination(technicians, { perPage: PER_PAGE })

  // Bersihkan form + pilihan tanda tangan (dipakai setelah simpan / batal).
  const resetForm = () => {
    setForm(emptyForm)
    setEditingTechnician(null)
    setSignatureFile(null)
    setSignaturePreview('')
    setRemoveSignature(false)
  }

  // Tanda tangan yang sedang dilihat: berkas baru (object URL), tanda tangan
  // lama di server, atau kosong bila sengaja dibuang.
  const currentSignaturePreview = () => {
    if (signatureFile) return signaturePreview
    if (removeSignature) return ''
    if (form.signature_path) return `/storage/${form.signature_path}`

    return ''
  }

  const handleSignatureChange = (event) => {
    const file = event.target.files?.[0] || null

    if (signaturePreview) URL.revokeObjectURL(signaturePreview)

    setSignatureFile(file)
    setSignaturePreview(file ? URL.createObjectURL(file) : '')
    setRemoveSignature(false)
  }

  const dropSignature = () => {
    if (signaturePreview) URL.revokeObjectURL(signaturePreview)

    setSignatureFile(null)
    setSignaturePreview('')
    setRemoveSignature(Boolean(form.signature_path))
  }

  // Data dikirim sebagai FormData karena tanda tangan diunggah sebagai berkas.
  const handleSubmit = async (event) => {
    event.preventDefault()
    setError('')
    setMessage('')
    setSubmitting(true)

    const payload = new FormData()
    payload.append('name', form.name)
    payload.append('nip', form.nip)
    payload.append('position', form.position)
    payload.append('whatsapp', form.whatsapp)
    payload.append('is_primary', form.is_primary ? '1' : '0')
    payload.append('remove_signature', removeSignature ? '1' : '0')
    if (signatureFile) payload.append('signature', signatureFile)

    try {
      const response = editingTechnician
        ? await api.put(`/technicians/${editingTechnician.id}`, payload)
        : await api.post('/technicians', payload)

      setMessage(response.data?.message || 'Data teknisi tersimpan.')
      resetForm()
      fetchTechnicians()
    } catch (requestError) {
      const data = requestError.response?.data
      setError(data?.errors ? Object.values(data.errors).flat().join(', ') : data?.message || 'Gagal menyimpan teknisi.')
    } finally {
      setSubmitting(false)
    }
  }

  const handleEdit = (technician) => {
    setEditingTechnician(technician)
    setForm({
      name: technician.name || '',
      position: technician.position || '',
      nip: technician.nip || '',
      whatsapp: technician.whatsapp || '',
      is_primary: Boolean(technician.is_primary),
      signature_path: technician.signature_path || '',
    })
    setSignatureFile(null)
    setSignaturePreview('')
    setRemoveSignature(false)
    setError('')
    setMessage('')
  }

  const cancelEdit = () => {
    resetForm()
    setError('')
  }

  const handleDelete = async (technician) => {
    if (!window.confirm(`Hapus teknisi "${technician.name}"?`)) return
    setError('')
    setMessage('')

    try {
      const response = await api.delete(`/technicians/${technician.id}`)
      setMessage(response.data?.message || 'Teknisi dihapus.')
      if (editingTechnician?.id === technician.id) resetForm()
      fetchTechnicians()
    } catch (requestError) {
      setError(requestError.response?.data?.message || 'Gagal menghapus teknisi.')
    }
  }

  return (
    <div className="max-w-6xl space-y-6">
      <header>
        <p className="text-sm font-semibold text-cyan-700">Administrasi laboratorium</p>
        <h1 className="mt-1 text-2xl font-bold text-slate-900">Kelola Teknisi</h1>
        <p className="mt-2 text-sm text-slate-500">
          Data teknisi dipakai sebagai pilihan penandatangan dokumen PDF: Laporan, Surat Bebas Labor, dan Peminjaman Skala Besar.
        </p>
      </header>

      {error && <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-600">{error}</div>}
      {message && !error && <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{message}</div>}

      <div className="grid gap-6 lg:grid-cols-[minmax(0,380px)_minmax(0,1fr)] lg:items-start">
        {/* ----------------------------- KOLOM KIRI: form tambah/ubah */}
        <div className="space-y-4 lg:sticky lg:top-6">
          <form onSubmit={handleSubmit} className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="mb-5 flex items-start gap-3">
              <span className="flex h-10 w-10 items-center justify-center rounded-lg bg-cyan-50 text-cyan-600">
                {editingTechnician ? <Pencil className="h-5 w-5" /> : <Plus className="h-5 w-5" />}
              </span>
              <div>
                <h2 className="font-semibold text-slate-900">{editingTechnician ? 'Ubah Teknisi' : 'Tambah Teknisi'}</h2>
                <p className="text-xs text-slate-500">
                  {editingTechnician ? 'Perbarui data penandatangan dokumen.' : 'Isi data singkat teknisi penandatangan.'}
                </p>
              </div>
            </div>

            <div className="space-y-4">
              <label className={labelClass}>
                Nama lengkap &amp; gelar
                <input
                  required
                  value={form.name}
                  onChange={(event) => setForm({ ...form, name: event.target.value })}
                  placeholder="Contoh: Nofa Hendrayana, S.T."
                  className={inputClass}
                />
              </label>

              <label className={labelClass}>
                NIP
                <input
                  required
                  value={form.nip}
                  onChange={(event) => setForm({ ...form, nip: event.target.value })}
                  placeholder="NIP teknisi"
                  className={inputClass}
                />
              </label>
              <label className={labelClass}>
                Jabatan / Peran Lab
                <input
                  value={form.position}
                  onChange={(event) => setForm({ ...form, position: event.target.value })}
                  placeholder="Contoh: Teknisi Lab Komputer / RPL"
                  className={inputClass}
                />
              </label>

              <label className={labelClass}>
                Nomor WhatsApp
                <input
                  value={form.whatsapp}
                  onChange={(event) => setForm({ ...form, whatsapp: event.target.value })}
                  placeholder="Contoh: 0812 3456 7890"
                  inputMode="tel"
                  className={inputClass}
                />
              </label>

              <div>
                <span className={labelClass}>Tanda tangan digital</span>
                {currentSignaturePreview() ? (
                  <>
                    <div className="mt-1.5 flex items-center gap-3 rounded-lg border border-dashed border-cyan-200 bg-cyan-50/50 p-3">
                      <img
                        src={currentSignaturePreview()}
                        alt="Pratinjau tanda tangan"
                        className="h-12 w-full max-w-[180px] object-contain"
                      />
                      <button
                        type="button"
                        onClick={dropSignature}
                        className="ml-auto inline-flex shrink-0 items-center gap-1 rounded-md border border-cyan-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-cyan-700 hover:bg-cyan-100"
                      >
                        <X className="h-3.5 w-3.5" />Hapus
                      </button>
                    </div>
                    <label className="mt-2 inline-flex cursor-pointer items-center gap-1.5 text-xs font-semibold text-cyan-700 hover:text-cyan-800">
                      <Upload className="h-3.5 w-3.5" />Ganti berkas
                      <input
                        type="file"
                        accept=".png,.jpg,.jpeg,image/png,image/jpeg"
                        onChange={handleSignatureChange}
                        className="sr-only"
                      />
                    </label>
                  </>
                ) : (
                  <label className="mt-1.5 flex cursor-pointer flex-col items-center justify-center gap-1 rounded-lg border border-dashed border-slate-300 bg-slate-50 px-3 py-5 text-center transition hover:border-cyan-400 hover:bg-cyan-50/40">
                    <Upload className="h-5 w-5 text-slate-400" />
                    <span className="text-xs font-medium text-slate-600">Pilih berkas tanda tangan</span>
                    <span className="text-[11px] text-slate-400">PNG atau JPG - maksimal 2 MB</span>
                    <input
                      type="file"
                      accept=".png,.jpg,.jpeg,image/png,image/jpeg"
                      onChange={handleSignatureChange}
                      className="sr-only"
                    />
                  </label>
                )}
                {removeSignature && !signatureFile && (
                  <p className="mt-2 text-[11px] text-amber-600">Tanda tangan lama akan dihapus saat disimpan.</p>
                )}
              </div>

              <label className="flex cursor-pointer items-start gap-2.5 rounded-lg border border-slate-200 bg-slate-50 p-3">
                <input
                  type="checkbox"
                  checked={form.is_primary}
                  onChange={(event) => setForm({ ...form, is_primary: event.target.checked })}
                  className="mt-0.5 h-4 w-4 rounded border-slate-300 text-cyan-600 focus:ring-cyan-500"
                />
                <span>
                  <span className="block text-sm font-semibold text-slate-800">Jadikan Teknisi Utama</span>
                  <span className="block text-xs text-slate-500">
                    Dipakai sebagai pilihan penandatangan pertama. Hanya satu teknisi yang dapat menjadi teknisi utama.
                  </span>
                </span>
              </label>
            </div>

            <div className="mt-5 flex gap-2">
              <button
                disabled={submitting}
                className="inline-flex flex-1 items-center justify-center gap-2 rounded-lg bg-cyan-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-cyan-700 disabled:opacity-50"
              >
                {editingTechnician ? <Pencil className="h-4 w-4" /> : <Plus className="h-4 w-4" />}
                {submitting ? 'Menyimpan...' : editingTechnician ? 'Simpan Perubahan' : 'Tambah Teknisi'}
              </button>
              {editingTechnician && (
                <button
                  type="button"
                  onClick={cancelEdit}
                  className="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50"
                >
                  Batal
                </button>
              )}
            </div>
          </form>
        </div>

        {/* ----------------------------- KOLOM KANAN: daftar teknisi */}
        <div className="space-y-4">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <h2 className="flex items-center gap-2 text-sm font-semibold text-slate-900">
              <UserRound className="h-4 w-4 text-cyan-600" />
              Teknisi Tersimpan
              <span className="rounded-full bg-cyan-50 px-2 py-0.5 text-xs font-semibold text-cyan-700">
                {technicians.length}
              </span>
            </h2>
            <p className="text-xs text-slate-500">
              {technicians.filter((technician) => technician.is_primary).length > 0
                ? 'Teknisi utama tampil paling atas.'
                : 'Belum ada teknisi utama.'}
            </p>
          </div>
          {loading ? (
            <div className="rounded-xl border border-slate-200 bg-white p-10 text-center text-sm text-slate-500">Memuat Data...</div>
          ) : technicians.length === 0 ? (
            <div className="rounded-xl border border-dashed border-slate-300 bg-white p-12 text-center">
              <UserRound className="mx-auto mb-3 h-10 w-10 text-slate-300" />
              <p className="text-sm font-semibold text-slate-700">Belum ada teknisi</p>
              <p className="mx-auto mt-1 max-w-sm text-xs text-slate-500">
                Isi formulir di samping untuk menambahkan teknisi pertama, lengkap dengan jabatan dan tanda tangan digitalnya.
              </p>
            </div>
          ) : (
            <div className="grid gap-4 sm:grid-cols-2">
              {technicianList.pageItems.map((technician, index) => (
                <article
                  key={technician.id}
                  className="flex flex-col rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-cyan-200 hover:shadow"
                >
                  <div className="flex items-start gap-3">
                    {/* Nomor urut teknisi, dilanjutkan antar halaman (11, 12, ...). */}
                    <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-slate-100 text-xs font-semibold text-slate-500">
                      {technicianList.rowOffset + index + 1}
                    </span>
                    <span
                      className={`flex h-12 w-12 shrink-0 items-center justify-center rounded-full text-base font-bold text-white ${
                        technician.is_primary
                          ? 'bg-gradient-to-br from-cyan-500 to-cyan-700'
                          : 'bg-gradient-to-br from-slate-400 to-slate-600'
                      }`}
                    >
                      {initialsOf(technician.name)}
                    </span>
                    <div className="min-w-0 flex-1">
                      <h3 className="truncate text-sm font-semibold text-slate-900" title={technician.name}>
                        {technician.name}
                      </h3>
                      {technician.position ? (
                        <p className="mt-0.5 truncate text-xs text-slate-500" title={technician.position}>
                          {technician.position}
                        </p>
                      ) : (
                        <p className="mt-0.5 text-xs italic text-slate-400">Jabatan belum diisi</p>
                      )}
                    </div>
                    {technician.is_primary ? (
                      <span className="inline-flex shrink-0 items-center gap-1 rounded-full bg-amber-100 px-2 py-1 text-[11px] font-semibold text-amber-800">
                        <BadgeCheck className="h-3.5 w-3.5" />Teknisi Utama
                      </span>
                    ) : (
                      <span className="inline-flex shrink-0 items-center gap-1 rounded-full bg-emerald-50 px-2 py-1 text-[11px] font-semibold text-emerald-700">
                        Aktif
                      </span>
                    )}
                  </div>

                  <dl className="mt-4 space-y-1.5 border-t border-slate-100 pt-3 text-xs">
                    <div className="flex items-center gap-2">
                      <dt className="w-16 shrink-0 text-slate-400">NIP</dt>
                      <dd className="truncate font-medium text-slate-700">{technician.nip}</dd>
                    </div>
                    <div className="flex items-center gap-2">
                      <dt className="w-16 shrink-0 text-slate-400">WhatsApp</dt>
                      <dd className="truncate text-slate-600">
                        {technician.whatsapp || <span className="italic text-slate-400">belum diisi</span>}
                      </dd>
                    </div>
                  </dl>

                  <div className="mt-3 flex items-end gap-3 rounded-lg border border-dashed border-slate-200 bg-slate-50 p-2.5">
                    {technician.signature_path ? (
                      <>
                        <img
                          src={`/storage/${technician.signature_path}`}
                          alt={`Tanda tangan ${technician.name}`}
                          className="h-10 w-full max-w-[150px] object-contain"
                        />
                        <span className="ml-auto inline-flex items-center gap-1 text-[11px] font-medium text-emerald-600">
                          <PenLine className="h-3.5 w-3.5" />Tanda tangan siap
                        </span>
                      </>
                    ) : (
                      <span className="inline-flex items-center gap-1.5 text-[11px] text-slate-400">
                        <PenLine className="h-3.5 w-3.5" />Belum ada tanda tangan digital
                      </span>
                    )}
                  </div>

                  <div className="mt-4 flex gap-2 border-t border-slate-100 pt-4">
                    <button
                      type="button"
                      onClick={() => handleEdit(technician)}
                      className="inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-cyan-200 px-3 py-2 text-xs font-semibold text-cyan-700 transition hover:bg-cyan-50"
                    >
                      <Pencil className="h-3.5 w-3.5" />Edit
                    </button>
                    <button
                      type="button"
                      onClick={() => handleDelete(technician)}
                      className="inline-flex items-center justify-center gap-1.5 rounded-lg border border-red-200 px-3 py-2 text-xs font-semibold text-red-600 transition hover:bg-red-50"
                    >
                      <Trash2 className="h-3.5 w-3.5" />Hapus
                    </button>
                  </div>
                </article>
              ))}
            </div>
          )}

          {technicians.length > 0 && (
            <TablePagination
              page={technicianList.page}
              lastPage={technicianList.lastPage}
              onPageChange={technicianList.goToPage}
              total={technicianList.total}
              perPage={PER_PAGE}
            />
          )}
        </div>
      </div>
    </div>
  )
}

export default Technicians




