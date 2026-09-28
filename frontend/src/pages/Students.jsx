import { useCallback, useEffect, useRef, useState } from 'react'
import { Mail, Pencil, Phone, Plus, RefreshCw, Search, Trash2, UserRound, Upload } from 'lucide-react'
import api from '../api/axios'
import ImportStudentsModal from '../components/ImportStudentsModal'

const emptyForm = { student_id: '', name: '', email: '', phone: '' }
const SYNC_INTERVAL_SECONDS = 5 * 60
// URL CSV Google Sheets terpublikasi yang menjadi sumber sinkronisasi otomatis.
const SYNC_URL_KEY = 'student_sync_csv_url'

function formatCountdown(seconds) {
  const minutes = Math.floor(seconds / 60)
  const remainder = seconds % 60

  return `${minutes}:${String(remainder).padStart(2, '0')}`
}

// Waktu sinkronisasi terakhir (jam:menit) untuk ditampilkan ke petugas.
function formatClock(value) {
  if (!value) return ''

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) return ''

  return date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
}

function Students() {
  const [students, setStudents] = useState([])
  const [form, setForm] = useState(emptyForm)
  const [search, setSearch] = useState('')
  const [editingStudent, setEditingStudent] = useState(null)
  const [loading, setLoading] = useState(true)
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')
  const [showImportModal, setShowImportModal] = useState(false)
  const [syncing, setSyncing] = useState(false)
  const [syncCountdown, setSyncCountdown] = useState(SYNC_INTERVAL_SECONDS)
  const syncingRef = useRef(false)
  // Ref berikut membuat timer otomatis selalu memakai versi terbaru dari
  // fungsi/data terkait, sehingga interval tidak perlu dibuat ulang tiap render
  // dan tidak memakai nilai lama (mis. kata kunci pencarian yang sudah berubah).
  const syncRef = useRef(null)
  const countdownRef = useRef(SYNC_INTERVAL_SECONDS)
  const searchRef = useRef('')
  const modalOpenRef = useRef(false)
  const [lastSyncedAt, setLastSyncedAt] = useState('')

  useEffect(() => { searchRef.current = search }, [search])
  useEffect(() => { modalOpenRef.current = showImportModal }, [showImportModal])

  const fetchStudents = useCallback(async (searchValue, silent = false) => {
    const term = String(searchValue ?? searchRef.current ?? '').trim()

    if (!silent) setLoading(true)

    try {
      const response = await api.get('/students', { params: term ? { search: term } : {} })
      setStudents(response.data.data || [])
    } catch (requestError) {
      setError(requestError.response?.data?.message || 'Gagal memuat data mahasiswa.')
    } finally {
      if (!silent) setLoading(false)
    }
  }, [])

  const syncStudents = useCallback(async (showFeedback = false) => {
    if (syncingRef.current) return false

    syncingRef.current = true
    setSyncing(true)

    try {
      let csvUrl = (localStorage.getItem(SYNC_URL_KEY) || '').trim()

      if (!csvUrl) {
        try {
          const source = await api.get('/students/import/source')
          csvUrl = String(source.data?.url || '').trim()
          if (csvUrl) localStorage.setItem(SYNC_URL_KEY, csvUrl)
        } catch {
          if (showFeedback) setError('URL spreadsheet belum dapat dimuat dari database.')

          return false
        }
      }

      if (!csvUrl) {
        // Sumber belum pernah diatur: bukan kegagalan, jadi tidak dipesan
        // berulang setiap siklus. Pesan hanya muncul saat refresh manual.
        if (showFeedback) setError('Belum ada URL CSV spreadsheet. Impor data melalui tombol Impor Spreadsheet terlebih dahulu.')

        return false
      }

      const result = window.desktop?.importStudentsFromCsv
        ? await window.desktop.importStudentsFromCsv(csvUrl)
        : (await api.post('/students/import/csv-url', { url: csvUrl }, { timeout: 30000 })).data

      if (!result?.ok) {
        // Kegagalan sinkronisasi otomatis wajib terlihat petugas. Bila dibisukan,
        // perubahan di spreadsheet tampak "tidak mau masuk" ke sistem tanpa sebab.
        setError(result?.message || 'Gagal menyinkronkan spreadsheet. Periksa URL/publikasi CSV.')

        return false
      }

      setError('')
      setLastSyncedAt(result.synced_at || new Date().toISOString())

      // Muat ulang daftar tanpa efek "Memuat..." agar tabel tidak berkedip
      // setiap siklus sinkronisasi, dan tetap mengikuti filter pencarian aktif.
      await fetchStudents(undefined, true)

      // Baris yang dilewati (mis. email bentrok/tidak valid) tetap
      // diberitahukan agar data yang tidak ikut masuk punya penjelasan.
      if (result.errors?.length) {
        setError(`${result.errors.length} baris spreadsheet dilewati saat sinkronisasi. ${result.errors[0]}`)
      }

      if (showFeedback) {
        const detail = [`${result.imported || 0} baru`, `${result.updated || 0} diperbarui`]

        if (result.unchanged) detail.push(`${result.unchanged} tanpa perubahan`)

        setSuccess(`Sinkronisasi selesai: ${detail.join(', ')}.`)
      }

      return true
    } catch (requestError) {
      setError(requestError.response?.data?.message || requestError.message || 'Gagal menyinkronkan spreadsheet.')

      return false
    } finally {
      syncingRef.current = false
      setSyncing(false)
    }
  }, [fetchStudents])

  // Daftar mahasiswa diambil sekali saat halaman dibuka, lalu dimuat ulang
  // (dengan jeda singkat) setiap kata kunci pencarian berubah.
  useEffect(() => { fetchStudents('') }, [fetchStudents])

  useEffect(() => {
    const timeout = window.setTimeout(() => fetchStudents(search), 300)

    return () => window.clearTimeout(timeout)
  }, [search, fetchStudents])

  // Versi terbaru syncStudents disimpan di ref agar timer otomatis di bawah
  // selalu memakai logika & data terbaru (tanpa membuat interval baru).
  useEffect(() => { syncRef.current = syncStudents }, [syncStudents])

  useEffect(() => {
    let cancelled = false

    // Sinkronisasi terakhir (tersimpan di database) ditampilkan sejak awal,
    // walaupun halaman baru dimuat ulang oleh petugas.
    api.get('/students/import/source')
      .then((response) => {
        if (cancelled) return

        const url = String(response.data?.url || '').trim()
        if (url) localStorage.setItem(SYNC_URL_KEY, url)
        if (response.data?.last_synced_at) setLastSyncedAt(response.data.last_synced_at)
      })
      .catch(() => {
        // URL belum tersimpan atau server belum tersedia.
      })

    return () => { cancelled = true }
  }, [])

  useEffect(() => {
    // Sinkronisasi pertama saat halaman dibuka, lalu diulang setiap
    // SYNC_INTERVAL_SECONDS selama halaman Data Mahasiswa terbuka. Perubahan
    // isi spreadsheet (nama, email, telepon, NIM) langsung ikut terbarui.
    syncRef.current?.()

    const interval = window.setInterval(() => {
      if (countdownRef.current > 1) {
        countdownRef.current -= 1
        setSyncCountdown(countdownRef.current)

        return
      }

      // Modal impor sedang terbuka: tunda satu siklus agar URL sumber yang
      // sedang diubah tidak tersinkron setengah jalan.
      if (modalOpenRef.current) return

      countdownRef.current = SYNC_INTERVAL_SECONDS
      setSyncCountdown(SYNC_INTERVAL_SECONDS)
      syncRef.current?.()
    }, 1000)

    return () => window.clearInterval(interval)
  }, [])

  const handleRefresh = async () => {
    countdownRef.current = SYNC_INTERVAL_SECONDS
    setSyncCountdown(SYNC_INTERVAL_SECONDS)
    setError('')
    setSuccess('')
    await syncStudents(true)
  }

  const handleImported = (result) => {
    setError('')
    if (result?.synced_at) setLastSyncedAt(result.synced_at)
    fetchStudents()
  }

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
      <div className="mb-8 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">Data Mahasiswa</h1>
          <p className="mt-1 text-slate-500">Simpan data mahasiswa agar pengisian peminjaman lebih cepat.</p>
        </div>
        <div className="flex shrink-0 flex-col items-stretch gap-2 sm:items-end">
          <div className="flex gap-2">
            <button
              type="button"
              onClick={handleRefresh}
              disabled={syncing}
              title="Refresh data dari spreadsheet"
              className="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2.5 font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50"
            >
              <RefreshCw className={`h-4 w-4 ${syncing ? 'animate-spin' : ''}`} />
              {syncing ? 'Memuat...' : 'Refresh Data'}
            </button>
            <button
              type="button"
              onClick={() => setShowImportModal(true)}
              className="inline-flex items-center gap-2 rounded-lg border border-cyan-600 px-4 py-2.5 font-medium text-cyan-700 hover:bg-cyan-50"
            >
              <Upload className="h-4 w-4" />
              Impor Spreadsheet
            </button>
          </div>
          <span className="text-right text-xs text-slate-500">
            <span>Refresh otomatis dalam {formatCountdown(syncCountdown)}</span>
            {lastSyncedAt && (
              <span className="block text-slate-400">Sinkron terakhir {formatClock(lastSyncedAt)}</span>
            )}
          </span>
        </div>
      </div>

      <ImportStudentsModal
        open={showImportModal}
        onClose={() => setShowImportModal(false)}
        onImported={handleImported}
      />

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
