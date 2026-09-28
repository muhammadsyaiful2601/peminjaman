import { useEffect, useRef, useState } from 'react'
import { DatabaseBackup, Download, Image, Info, KeyRound, Mail, Save, Server, Settings2, Upload, X } from 'lucide-react'
import api from '../api/axios'
import { useBranding } from '../context/BrandingContext'
import { downloadBlob, filenameFromResponse, timestampedFilename } from '../utils/downloadBlob'
import HybridSyncSection from '../components/settings/HybridSyncSection'
import EmailSettingsSection from '../components/settings/EmailSettingsSection'
import UpdateSettingsSection from '../components/settings/UpdateSettingsSection'

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

// Semua konfigurasi aplikasi dikumpulkan di satu halaman. Bagian khusus
// desktop (Hosting & Sinkronisasi, Tentang & Pembaruan) disembunyikan di web.
const SECTIONS = [
  { key: 'identity', label: 'Identitas Aplikasi', icon: Settings2, desktopOnly: false },
  { key: 'email', label: 'Email (SMTP)', icon: Mail, desktopOnly: false },
  { key: 'sync', label: 'Hosting & Sinkronisasi', icon: Server, desktopOnly: true },
  { key: 'backup', label: 'Backup & Pemulihan', icon: DatabaseBackup, desktopOnly: false },
  { key: 'about', label: 'Tentang & Pembaruan', icon: Info, desktopOnly: true },
]

function formatDateTime(value) {
  if (!value) return '-'
  try {
    return new Date(value).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' })
  } catch {
    return value
  }
}

// Ukuran berkas ramah-baca untuk keterangan backup lengkap.
function formatBytes(bytes) {
  if (!bytes) return '0 B'

  const units = ['B', 'KB', 'MB', 'GB']
  let value = Number(bytes)
  let index = 0

  while (value >= 1024 && index < units.length - 1) {
    value /= 1024
    index += 1
  }

  return `${index === 0 ? value : value.toFixed(1)} ${units[index]}`
}

function SystemSettings() {
  const branding = useBranding()
  const isDesktop = Boolean(window.desktop?.isDesktop)
  const [tab, setTab] = useState('identity')
  const [form, setForm] = useState(branding)
  const [files, setFiles] = useState({})
  const [saving, setSaving] = useState(false)
  const [message, setMessage] = useState('')
  const [error, setError] = useState('')
  const [backupStatus, setBackupStatus] = useState(null)
  const [backupLoading, setBackupLoading] = useState('')
  const [backupType, setBackupType] = useState('')
  const [backupPassword, setBackupPassword] = useState('')
  const [restoreFile, setRestoreFile] = useState(null)
  const [restorePassword, setRestorePassword] = useState('')
  const [restoring, setRestoring] = useState(false)
  const [restoreMessage, setRestoreMessage] = useState('')
  const [restoreError, setRestoreError] = useState('')
  // Peringatan pemulihan (mis. foto tidak ikut dipulihkan) dipisahkan dari
  // pesan sukses agar mudah dikenali dan tidak dianggap "semuanya beres".
  const [restoreWarnings, setRestoreWarnings] = useState([])
  const [finalizing, setFinalizing] = useState(false)
  const restoreInputRef = useRef(null)

  // Tab khusus desktop disembunyikan di web; bila tab aktif tidak tersedia
  // (mis. jendela dibuka di versi web), kembali ke tab pertama.
  const visibleSections = SECTIONS.filter((section) => !section.desktopOnly || isDesktop)
  const activeTab = visibleSections.some((section) => section.key === tab) ? tab : 'identity'

  useEffect(() => {
    setForm(branding)
  }, [branding])

  useEffect(() => {
    api.get('/backups/status').then((response) => setBackupStatus(response.data)).catch(() => {})
  }, [])

  const updateField = (key, value) => setForm((current) => ({ ...current, [key]: value }))

  const openBackupConfirmation = (type) => {
    setError('')
    setBackupPassword('')
    setBackupType(type)
  }

  const downloadBackup = async (event) => {
    event.preventDefault()
    if (!backupType || !backupPassword) return

    const type = backupType
    setBackupLoading(type)
    setError('')
    try {
      const response = await api.post(`/backups/${type}`, { password: backupPassword }, { responseType: 'blob' })
      // Nama berkas mengikuti backend (sudah berisi tanggal & jam), mis.
      // backup-lengkap-2026-09-28-142530.zip, agar tiap backup punya nama
      // sendiri dan tidak saling menimpa.
      const fallbackName = timestampedFilename(
        type === 'full' ? 'backup-lengkap' : type === 'mysql' ? 'backup-mysql' : 'backup-sqlite',
        type === 'full' ? 'zip' : type === 'mysql' ? 'sql' : 'sqlite',
      )
      const result = await downloadBlob(response.data, filenameFromResponse(response, fallbackName))
      if (result && !result.ok && !result.canceled) setError(result.message || 'Backup gagal disimpan.')
      if (!result || result.ok || result.canceled) {
        setBackupType('')
        setBackupPassword('')
      }
    } catch (err) {
      let responseData = err.response?.data
      if (responseData instanceof Blob) {
        try {
          responseData = JSON.parse(await responseData.text())
        } catch {
          responseData = null
        }
      }
      setError(responseData?.message || 'Password salah atau backup database gagal dibuat.')
    } finally {
      setBackupLoading('')
    }
  }

  /**
   * Pulihkan database (dan foto bila berkas backup berupa arsip lengkap).
   * Data saat ini ditimpa, karena itu pengguna dikonfirmasi lebih dulu dan
   * halaman dimuat ulang setelah pemulihan berhasil.
   */
  const handleRestore = async (event) => {
    event.preventDefault()
    setRestoreMessage('')
    setRestoreError('')
    setRestoreWarnings([])

    if (!restoreFile) {
      setRestoreError('Pilih berkas backup (.zip/.sqlite/.sql) terlebih dahulu.')
      return
    }

    if (!restorePassword) {
      setRestoreError('Masukkan password admin untuk melanjutkan pemulihan.')
      return
    }

    const confirmation = window.confirm(
      'Seluruh data yang ada sekarang akan digantikan oleh isi berkas backup.\n\nLanjutkan pemulihan database?',
    )
    if (!confirmation) return

    setRestoring(true)

    try {
      const payload = new FormData()
      payload.append('file', restoreFile)
      payload.append('password', restorePassword)

      const response = await api.post('/backups/restore', payload, { timeout: 15 * 60 * 1000 })

      setRestoreMessage(response.data?.message || 'Database berhasil dipulihkan.')
      setRestoreWarnings(response.data?.warnings || [])
      setRestorePassword('')
      setRestoreFile(null)
      if (restoreInputRef.current) restoreInputRef.current.value = ''

      setRestoring(false)
      await finalizeRestore()
    } catch (err) {
      const data = err.response?.data
      setRestoreError(data?.errors ? Object.values(data.errors).flat().join(', ') : data?.message || 'Pemulihan database gagal.')
      setRestoring(false)
    }
  }

  /**
   * Selesaikan pemulihan: rapikan skema lalu muat ulang halaman.
   *
   * Data hasil backup bisa berasal dari versi aplikasi yang lebih lama, sehingga
   * tabel/kolom baru belum ada. Aplikasi desktop menjalankan migrasi hanya saat
   * boot, jadi tanpa langkah ini beberapa halaman tetap bermasalah sampai
   * aplikasi ditutup dan dibuka ulang. Di aplikasi desktop, migrasi +
   * restart server dijalankan lewat jembatan desktop (window.desktop).
   */
  const finalizeRestore = async () => {
    setFinalizing(true)
    setRestoreMessage((current) => current || 'Memuat ulang aplikasi...')

    if (isDesktop && typeof window.desktop?.afterRestore === 'function') {
      let failure = null

      try {
        const result = await window.desktop.afterRestore()
        if (result && result.ok === false) {
          failure = result.message || 'Migrasi setelah pemulihan belum selesai.'
        }
      } catch {
        // Jembatan desktop tidak tersedia: lanjutkan seperti mode web.
        failure = null
      }

      if (failure !== null) {
        // Penyiapan gagal: jangan paksa muat ulang karena gejalanya persis
        // "halaman tidak berfungsi" — tampilkan peringatan + tombol manual.
        setRestoreWarnings((current) => [
          ...current,
          failure,
        ])
        setFinalizing(false)

        return
      }
    }

    // Seluruh isi aplikasi berganti: muat ulang agar tampilan memakai data
    // hasil pemulihan (dan login ulang bila akun ikut berubah).
    window.location.reload()
  }

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
        <p className="mt-2 text-sm text-slate-500">Kelola identitas aplikasi, email, hosting, backup, dan pembaruan dalam satu halaman.</p>
      </div>

      {/* NAVIGASI TAB - semua konfigurasi aplikasi dikumpulkan di satu halaman */}
      <nav className="flex flex-wrap gap-2 border-b border-slate-200">
        {visibleSections.map((section) => {
          const Icon = section.icon
          const active = activeTab === section.key

          return (
            <button
              key={section.key}
              type="button"
              onClick={() => setTab(section.key)}
              className={`-mb-px inline-flex items-center gap-1.5 border-b-2 px-3.5 py-2.5 text-sm font-semibold transition ${
                active
                  ? 'border-cyan-600 text-cyan-700'
                  : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700'
              }`}
            >
              <Icon className="h-4 w-4" />
              {section.label}
            </button>
          )
        })}
      </nav>

      <form onSubmit={handleSubmit} className="space-y-6">
        {activeTab === 'identity' && (
          <>
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
          </>
        )}

        {activeTab === 'email' && <EmailSettingsSection />}

        {activeTab === 'sync' && <HybridSyncSection />}

        {activeTab === 'backup' && (
        <section className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
          <div className="mb-5 flex items-center gap-3">
            <DatabaseBackup className="h-5 w-5 text-cyan-600" />
            <div>
              <h2 className="font-semibold text-slate-900">Backup Database</h2>
              <p className="text-sm text-slate-500">Unduh salinan database, atau backup lengkap berisi database beserta seluruh foto.</p>
            </div>
          </div>
          <div className="flex flex-wrap gap-3">
            {backupStatus?.full && (
              <button type="button" onClick={() => openBackupConfirmation('full')} disabled={!!backupLoading} className="inline-flex items-center gap-2 rounded-lg bg-cyan-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-cyan-700 disabled:opacity-50">
                <DatabaseBackup className="h-4 w-4" />
                {backupLoading === 'full' ? 'Menyiapkan arsip...' : 'Backup Lengkap (Database + Foto)'}
              </button>
            )}
            {backupStatus?.sqlite && (
              <button type="button" onClick={() => openBackupConfirmation('sqlite')} disabled={!!backupLoading} className="inline-flex items-center gap-2 rounded-lg border border-cyan-200 px-4 py-2.5 text-sm font-semibold text-cyan-700 hover:bg-cyan-50 disabled:opacity-50">
                <Download className="h-4 w-4" />
                {backupLoading === 'sqlite' ? 'Menyiapkan SQLite...' : 'Download SQLite'}
              </button>
            )}
            {backupStatus?.mysql && (
              <button type="button" onClick={() => openBackupConfirmation('mysql')} disabled={!!backupLoading} className="inline-flex items-center gap-2 rounded-lg border border-emerald-200 px-4 py-2.5 text-sm font-semibold text-emerald-700 hover:bg-emerald-50 disabled:opacity-50">
                <Download className="h-4 w-4" />
                {backupLoading === 'mysql' ? 'Menyiapkan MySQL...' : 'Download MySQL'}
              </button>
            )}
          </div>
          <p className="mt-3 text-xs text-slate-500">
            {backupStatus?.hybrid ? 'Mode hybrid aktif: ketiga backup tersedia.' : backupStatus?.driver === 'mysql' ? 'Mode MySQL aktif.' : 'Mode SQLite aktif.'}
          </p>
          {backupStatus?.photos && (
            <p className="mt-1 text-xs text-slate-500">
              Backup lengkap berisi {backupStatus.photos.files} berkas foto/gambar ({formatBytes(backupStatus.photos.bytes)}) dan dapat dipulihkan lewat bagian <strong>Impor / Pulihkan Database</strong> di bawah.
            </p>
          )}
          {backupStatus?.reminder && (
            <p className="mt-1 text-xs text-slate-500">
              {backupStatus.reminder.last_backup_at
                ? `Backup terakhir dibuat ${formatDateTime(backupStatus.reminder.last_backup_at)}.`
                : 'Belum pernah membuat backup.'}{' '}
              {backupStatus.reminder.due
                ? 'Pengingat otomatis akan muncul lagi saat aplikasi dibuka.'
                : `Pengingat otomatis muncul lagi ${backupStatus.reminder.interval_days} hari setelah backup terakhir.`}
            </p>
          )}
        </section>
        )}

        {activeTab === 'identity' && (
          <>
            {message && <p className="rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{message}</p>}
            {error && <p className="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{error}</p>}
            <button disabled={saving} className="inline-flex items-center gap-2 rounded-lg bg-cyan-600 px-5 py-2.5 font-semibold text-white hover:bg-cyan-700 disabled:opacity-50"><Save className="h-4 w-4" />{saving ? 'Menyimpan...' : 'Simpan pengaturan'}</button>
          </>
        )}
      </form>

      {activeTab === 'backup' && (
      <form onSubmit={handleRestore} className="rounded-xl border border-amber-200 bg-white p-6 shadow-sm">
        <div className="mb-5 flex items-center gap-3">
          <Upload className="h-5 w-5 text-amber-600" />
          <div>
            <h2 className="font-semibold text-slate-900">Impor / Pulihkan Database</h2>
            <p className="text-sm text-slate-500">
              Kembalikan isi aplikasi dari berkas backup: arsip <code className="rounded bg-slate-100 px-1">.zip</code> hasil
              <strong> Backup Lengkap</strong> (database + foto), atau berkas <code className="rounded bg-slate-100 px-1">.sqlite</code> /
              <code className="rounded bg-slate-100 px-1">.sql</code> (database saja).
            </p>
          </div>
        </div>

        <div className="mt-4 grid gap-4 md:grid-cols-2">
          <label className="text-sm font-medium text-slate-700">
            Berkas backup
            <input
              ref={restoreInputRef}
              type="file"
              accept=".zip,.sqlite,.sqlite3,.db,.sql,application/zip,application/octet-stream,application/sql"
              onChange={(event) => {
                setRestoreFile(event.target.files?.[0] || null)
                setRestoreMessage('')
                setRestoreError('')
                setRestoreWarnings([])
              }}
              className="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200"
            />
          </label>
          <label className="text-sm font-medium text-slate-700">
            Password admin
            <input
              type="password"
              value={restorePassword}
              onChange={(event) => setRestorePassword(event.target.value)}
              placeholder="Masukkan password admin"
              className="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-100"
            />
          </label>
        </div>

        {restoreMessage && (
          <div className="mt-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            <p>{restoreMessage}</p>
            {restoreWarnings.map((warning) => (
              <p key={warning} className="mt-2 rounded-md bg-amber-50 px-3 py-2 text-amber-800">
                <strong className="font-semibold">Perhatian: </strong>
                {warning}
              </p>
            ))}
            {finalizing ? (
              <p className="mt-2 text-xs text-emerald-700">Menyiapkan aplikasi lalu memuat ulang halaman...</p>
            ) : (
              <button
                type="button"
                onClick={finalizeRestore}
                className="mt-2 rounded-md border border-emerald-300 px-3 py-1.5 text-xs font-semibold text-emerald-800 hover:bg-emerald-100"
              >
                Muat ulang sekarang
              </button>
            )}
          </div>
        )}
        {restoreError && <p className="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{restoreError}</p>}

        <button
          type="submit"
          disabled={restoring || finalizing}
          className="mt-5 inline-flex items-center gap-2 rounded-lg bg-amber-600 px-5 py-2.5 font-semibold text-white hover:bg-amber-700 disabled:opacity-50"
        >
          <Upload className={`h-4 w-4 ${restoring ? 'animate-pulse' : ''}`} />
          {restoring ? 'Memulihkan data...' : 'Pulihkan Data Sekarang'}
        </button>
        {(restoring || finalizing) && (
          <p className="mt-2 text-xs text-slate-500">
            {restoring
              ? 'Proses ini dapat memakan waktu beberapa menit bila arsip memuat banyak foto. Jangan tutup jendela aplikasi.'
              : 'Menjalankan migrasi database lalu memuat ulang aplikasi...'}
          </p>
        )}
      </form>
      )}

      {activeTab === 'about' && (
        <section className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
          <div className="mb-5">
            <h2 className="font-semibold text-slate-900">Tentang Aplikasi</h2>
            <p className="text-sm text-slate-500">Versi, kanal rilis, dan pemutakhiran aplikasi desktop.</p>
          </div>
          <UpdateSettingsSection />
        </section>
      )}

      {backupType && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4">
          <form onSubmit={downloadBackup} className="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
            <div className="flex items-start justify-between gap-4">
              <div className="flex items-start gap-3">
                <KeyRound className="mt-0.5 h-5 w-5 text-cyan-600" />
                <div>
                  <h2 className="font-semibold text-slate-900">Konfirmasi Password</h2>
                  <p className="mt-1 text-sm text-slate-500">
                    Masukkan password admin untuk mengunduh {backupType === 'full' ? 'backup lengkap (database + seluruh foto)' : backupType === 'mysql' ? 'backup MySQL' : 'backup SQLite'}.
                  </p>
                </div>
              </div>
              <button type="button" onClick={() => setBackupType('')} className="text-slate-400 hover:text-slate-600" aria-label="Tutup"><X className="h-5 w-5" /></button>
            </div>
            <input autoFocus type="password" value={backupPassword} onChange={(event) => setBackupPassword(event.target.value)} placeholder="Masukkan Password Admin" className="mt-5 w-full rounded-lg border border-slate-300 px-3 py-2.5 outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-100" required />
            {error && <p className="mt-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{error}</p>}
            <div className="mt-5 flex justify-end gap-2">
              <button type="button" onClick={() => setBackupType('')} className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Batal</button>
              <button type="submit" disabled={!!backupLoading} className="inline-flex items-center gap-2 rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700 disabled:opacity-50"><Download className="h-4 w-4" />{backupLoading ? 'Menyiapkan...' : 'Konfirmasi & Download'}</button>
            </div>
          </form>
        </div>
      )}
    </div>
  )
}

export default SystemSettings

