import { useEffect, useState } from 'react'
import { Image, Save, Settings2, Upload } from 'lucide-react'
import api from '../api/axios'
import { useBranding } from '../context/BrandingContext'

const textFields = [
  ['app_name', 'Nama aplikasi'],
  ['organization_ministry', 'Baris kop surat 1'],
  ['organization_name', 'Nama instansi'],
  ['organization_unit', 'Unit / program'],
  ['organization_department', 'Jurusan / program studi'],
  ['organization_address', 'Alamat kop surat'],
  ['login_description', 'Deskripsi halaman login'],
]

const fileFields = [
  ['app_logo', 'Logo aplikasi', 'app_logo_path', 'Logo yang tampil di login dan menu aplikasi.'],
  ['landing_photo', 'Foto halaman depan', 'landing_photo_path', 'Foto yang tampil di sisi kiri halaman login.'],
  ['letterhead_logo', 'Logo kop surat', 'letterhead_logo_path', 'Logo yang tampil pada laporan dan surat PDF.'],
]

function SystemSettings() {
  const branding = useBranding()
  const [form, setForm] = useState(branding)
  const [files, setFiles] = useState({})
  const [saving, setSaving] = useState(false)
  const [message, setMessage] = useState('')
  const [error, setError] = useState('')

  useEffect(() => {
    setForm(branding)
  }, [branding])

  const updateField = (key, value) => setForm((current) => ({ ...current, [key]: value }))

  const handleSubmit = async (event) => {
    event.preventDefault()
    setSaving(true)
    setMessage('')
    setError('')
    const payload = new FormData()
    textFields.forEach(([key]) => payload.append(key, form[key] || ''))
    fileFields.forEach(([key]) => {
      if (files[key]) payload.append(key, files[key])
    })

    try {
      const response = await api.post('/branding', payload)
      branding.setBranding((current) => ({ ...current, ...response.data.branding }))
      setFiles({})
      setMessage(response.data.message)
    } catch (err) {
      const data = err.response?.data
      setError(data?.errors ? Object.values(data.errors).flat().join(', ') : data?.message || 'Gagal menyimpan pengaturan.')
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="max-w-4xl space-y-6">
      <div>
        <p className="text-sm font-semibold text-cyan-700">Administrasi aplikasi</p>
        <h1 className="mt-1 text-2xl font-bold text-slate-900">Pengaturan Sistem</h1>
        <p className="mt-2 text-sm text-slate-500">Sesuaikan identitas aplikasi untuk setiap organisasi atau pelanggan.</p>
      </div>

      <form onSubmit={handleSubmit} className="space-y-6">
        <section className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
          <div className="mb-5 flex items-center gap-3">
            <Settings2 className="h-5 w-5 text-cyan-600" />
            <div><h2 className="font-semibold text-slate-900">Identitas aplikasi</h2><p className="text-sm text-slate-500">Teks ini digunakan pada login, navigasi, dan dokumen.</p></div>
          </div>
          <div className="grid gap-4 md:grid-cols-2">
            {textFields.map(([key, label]) => (
              <label key={key} className={key === 'login_description' ? 'md:col-span-2' : ''}>
                <span className="mb-1.5 block text-sm font-medium text-slate-700">{label}</span>
                {key === 'login_description' ? (
                  <textarea value={form[key] || ''} onChange={(event) => updateField(key, event.target.value)} rows={2} className="w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-100" />
                ) : (
                  <input value={form[key] || ''} onChange={(event) => updateField(key, event.target.value)} className="w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-100" />
                )}
              </label>
            ))}
          </div>
        </section>

        <section className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
          <div className="mb-5 flex items-center gap-3"><Image className="h-5 w-5 text-cyan-600" /><div><h2 className="font-semibold text-slate-900">Aset visual</h2><p className="text-sm text-slate-500">PNG, JPG, atau WEBP. Logo maksimal 3 MB dan foto maksimal 5 MB.</p></div></div>
          <div className="grid gap-4 md:grid-cols-3">
            {fileFields.map(([key, label, previewKey, hint]) => (
              <label key={key} className="cursor-pointer rounded-lg border border-dashed border-slate-300 p-4 hover:border-cyan-400">
                <span className="block text-sm font-medium text-slate-700">{label}</span>
                <span className="mt-1 block text-xs text-slate-500">{hint}</span>
                <div className="my-4 flex h-28 items-center justify-center overflow-hidden rounded-lg bg-slate-50">
                  {(files[key] ? URL.createObjectURL(files[key]) : form[previewKey]) ? <img src={files[key] ? URL.createObjectURL(files[key]) : form[previewKey]} alt={label} className="h-full w-full object-contain" /> : <Upload className="h-7 w-7 text-slate-400" />}
                </div>
                <span className="block truncate text-xs text-cyan-700">{files[key]?.name || 'Pilih file baru'}</span>
                <input type="file" accept="image/png,image/jpeg,image/webp" className="sr-only" onChange={(event) => setFiles((current) => ({ ...current, [key]: event.target.files?.[0] }))} />
              </label>
            ))}
          </div>
        </section>

        {message && <p className="rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{message}</p>}
        {error && <p className="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{error}</p>}
        <button disabled={saving} className="inline-flex items-center gap-2 rounded-lg bg-cyan-600 px-5 py-2.5 font-semibold text-white hover:bg-cyan-700 disabled:opacity-50"><Save className="h-4 w-4" />{saving ? 'Menyimpan...' : 'Simpan pengaturan'}</button>
      </form>
    </div>
  )
}

export default SystemSettings
