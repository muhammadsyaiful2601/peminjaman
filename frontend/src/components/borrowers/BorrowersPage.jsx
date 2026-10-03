import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { Mail, Pencil, Phone, Plus, RefreshCw, Search, Trash2, UserRound, Upload } from 'lucide-react'
import api from '../../api/axios'
import TablePagination from '../TablePagination'
import ImportStudentsModal from '../ImportStudentsModal'
import { BORROWER_TYPES, DEFAULT_BORROWER_TYPE, SPREADSHEET_BORROWER_TYPES, borrowerTypeBadgeClass, borrowerTypeLabel, employeeTypeFromPosition, identityLabel, normalizeBorrowerType, spreadsheetSyncGroups, syncStorageKey } from '../../utils/borrowerTypes'

const emptyForm = { student_id: '', name: '', type: DEFAULT_BORROWER_TYPE, role: '', position: '', email: '', phone: '' }
const SYNC_INTERVAL_SECONDS = 5 * 60
// Jumlah baris per halaman pada tabel Data Peminjam. Setelah 10 data
// muncul tombol "Berikutnya" supaya petugas bisa membuka halaman berikutnya.
const PER_PAGE = 10
// Status awal sumber sinkronisasi per jenis peminjam. Mahasiswa, tendik, dan
// dosen masing-masing punya spreadsheet sendiri dengan logika yang sama.
const emptySyncSources = () => SPREADSHEET_BORROWER_TYPES.reduce((acc, type) => {
  acc[type.value] = { url: '', lastSyncedAt: '' }

  return acc
}, {})

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

/**
 * Halaman pengelolaan data peminjam.
 *
 * Satu komponen dipakai ulang oleh dua halaman supaya logikanya benar-benar
 * identik — yang berbeda hanya kelompok yang ditampilkan:
 *
 *   - **Data Mahasiswa** → `lockedType="mahasiswa"` (tanpa tab, jenis terkunci).
 *   - **Data Tendik, Dosen & Umum** → `allowedTypes={['tendik', 'dosen', 'umum']}`
 *     memakai tab per kelompok.
 *
 * @param {object}   props
 * @param {string}   props.title          Judul halaman.
 * @param {string}   props.subtitle       Keterangan di bawah judul.
 * @param {string}   [props.lockedType]   Jenis yang dipaksa (tanpa tab jenis).
 * @param {string[]} [props.allowedTypes] Jenis yang boleh tampil pada tab.
 * @param {string}   [props.addHint]      Petunjuk singkat di bawah form tambah.
 */
export default function BorrowersPage({
  title,
  subtitle,
  lockedType = null,
  allowedTypes = null,
  addHint = null,
}) {
  // Kelompok yang tampil di halaman ini. Halaman mahasiswa memakai satu jenis
  // terkunci; halaman pegawai memakai tab.
  //
  // Penentuannya lewat string (bukan langsung dari `allowedTypes`) supaya
  // identitas `scope` stabil walau pemanggil menulis array literal
  // (`allowedTypes={['tendik','dosen','umum']}` membuat array baru tiap render).
  // Tanpa ini, efek sinkronisasi ikut berjalan ulang setiap render.
  const scopeKey = lockedType || (allowedTypes?.length ? allowedTypes.join(',') : DEFAULT_BORROWER_TYPE)
  const scope = useMemo(() => scopeKey.split(','), [scopeKey])

  const isLocked = Boolean(lockedType)
  // Kelompok spreadsheet yang dibaca halaman ini. Tendik & Dosen satu grup
  // karena memakai spreadsheet yang sama — tanpa itu keduanya menarik CSV
  // yang sama dua kali per siklus dan jumlah barisnya terhitung dobel.
  const syncGroups = useMemo(
    () => spreadsheetSyncGroups(scope),
    [scopeKey], // eslint-disable-line react-hooks/exhaustive-deps
  )
  // Seluruh jenis yang tercakup grup di atas (untuk badge & pemuatan status).
  const syncTypes = useMemo(
    () => syncGroups.flatMap((group) => group.types),
    [syncGroups],
  )

  const [students, setStudents] = useState([])
  // Jenis default form mengikuti kelompok pertama pada halaman ini (mahasiswa
  // untuk halaman mahasiswa, tendik untuk halaman pegawai).
  const [form, setForm] = useState(() => ({ ...emptyForm, type: scope[0] || DEFAULT_BORROWER_TYPE }))
  const [search, setSearch] = useState('')
  const [editingStudent, setEditingStudent] = useState(null)
  const [loading, setLoading] = useState(true)
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')
  const [showImportModal, setShowImportModal] = useState(false)
  const [syncing, setSyncing] = useState(false)
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [total, setTotal] = useState(0)
  // Tab jenis peminjam: pada halaman terkunci selalu berisi jenisnya, pada
  // halaman bertab bisa '' (Semua) atau salah satu dari `scope`.
  const [typeFilter, setTypeFilter] = useState(isLocked ? lockedType : '')
  const [counts, setCounts] = useState({ all: 0 })
  const syncingRef = useRef(false)
  // Ref berikut membuat timer otomatis selalu memakai versi terbaru dari
  // fungsi/data terkait, sehingga interval tidak perlu dibuat ulang tiap render
  // dan tidak memakai nilai lama (mis. kata kunci pencarian yang sudah berubah).
  const syncRef = useRef(null)
  const searchRef = useRef('')
  const pageRef = useRef(1)
  const typeRef = useRef(isLocked ? lockedType : '')
  // Lingkup halaman (kelompok yang ditangani) disimpan di ref supaya
  // `fetchStudents` tetap stabil namun selalu membaca nilai terbaru.
  const scopeRef = useRef(scope)
  // Efek perpindahan halaman melewati run pertama (pemuatan awal sudah
  // ditangani efek lain), lalu setiap perpindahan halaman berikutnya tetap
  // mengambil data.
  const skipPageFetchRef = useRef(true)
  const skipTypeFetchRef = useRef(true)
  const modalOpenRef = useRef(false)
  // Sumber spreadsheet per jenis: { [type]: { url, lastSyncedAt } }.
  const [syncSources, setSyncSources] = useState(emptySyncSources)

  useEffect(() => { searchRef.current = search }, [search])
  useEffect(() => { pageRef.current = page }, [page])
  useEffect(() => { typeRef.current = typeFilter }, [typeFilter])
  useEffect(() => { scopeRef.current = scope }, [scope])
  useEffect(() => { modalOpenRef.current = showImportModal }, [showImportModal])

  const fetchStudents = useCallback(async (searchValue, silent = false, pageValue = null) => {
    const term = String(searchValue ?? searchRef.current ?? '').trim()
    // Halaman tujuan: argumen eksplisit (dipakai saat reset ke halaman 1),
    // selain itu mengikuti halaman yang sedang aktif.
    const currentPage = Number(pageValue) > 0 ? Number(pageValue) : pageRef.current
    // Jenis yang diminta: tab yang aktif, atau SELURUH kelompok halaman ini
    // saat tab "Semua" dipilih. Wajib dikirim walau hanya satu jenis supaya
    // halaman yang jenisnya terkunci tidak pernah mengambil jenis lain.
    const typeValue = typeRef.current
    const requestedTypes = typeValue ? [typeValue] : scopeRef.current

    if (!silent) setLoading(true)

    try {
      const params = { page: currentPage, per_page: PER_PAGE, type: requestedTypes.join(',') }
      if (term) params.search = term
      const response = await api.get('/students', { params })
      setStudents(response.data.data || [])
      setLastPage(response.data.meta?.last_page || 1)
      setTotal(response.data.meta?.total || 0)
      setCounts({
        ...(response.data.meta?.by_type || {}),
        // Angka tab "Semua" hanya menjumlahkan kelompok halaman ini, bukan
        // seluruh peminjam di database.
        scope: response.data.meta?.scope_total ?? 0,
      })
    } catch (requestError) {
      setError(requestError.response?.data?.message || 'Gagal memuat data peminjam.')
    } finally {
      if (!silent) setLoading(false)
    }
  }, [])

  /**
   * Sinkronisasi satu jenis peminjam dari Google Sheets-nya. Logikanya sama
   * persis dengan mahasiswa: baca URL (cache browser dulu, lalu database),
   * tarik CSV terpublikasi, lalu hitung baris baru/diubah/tetap.
   *
   * @returns {Promise<{ok: boolean, skipped?: boolean, message?: string, label?: string, imported?: number, updated?: number, unchanged?: number, errors?: string[]}>}
   */
  const syncOneGroup = useCallback(async (group) => {
    // Kelompok gabungan (Tendik & Dosen) memakai satu spreadsheet, jadi cukup
    // satu request. Jenis yang dikirim ke backend menentukan spreadsheet mana
    // yang dibaca, sedangkan kolom "Jenis" pada sheet memisahkan barisnya.
    const type = group.types[0]
    const label = group.label
    const storageKey = syncStorageKey(type)
    let csvUrl = (localStorage.getItem(storageKey) || '').trim()

    if (!csvUrl) {
      try {
        const source = await api.get('/students/import/source', { params: { type } })
        csvUrl = String(source.data?.url || '').trim()
        if (csvUrl) localStorage.setItem(storageKey, csvUrl)
      } catch {
        return { ok: false, skipped: true, label, message: `URL spreadsheet ${label.toLowerCase()} belum dapat dimuat dari database.` }
      }
    }

    if (!csvUrl) {
      // Sumber belum pernah diatur: bukan kegagalan, jadi tidak dipesan
      // berulang setiap siklus. Pesan hanya muncul saat refresh manual.
      return { ok: false, skipped: true, label, message: `Belum ada URL CSV spreadsheet ${label.toLowerCase()}.` }
    }

    const result = window.desktop?.importStudentsFromCsv
      ? await window.desktop.importStudentsFromCsv(csvUrl, type)
      : (await api.post('/students/import/csv-url', { url: csvUrl, type }, { timeout: 30000 })).data

    if (!result?.ok) {
      return { ok: false, label, message: result?.message || `Gagal menyinkronkan spreadsheet ${label.toLowerCase()}.` }
    }

    return {
      ok: true,
      label,
      groupKey: group.key,
      types: group.types,
      imported: result.imported || 0,
      updated: result.updated || 0,
      unchanged: result.unchanged || 0,
      errors: result.errors || [],
      syncedAt: result.synced_at || new Date().toISOString(),
      message: result.message,
    }
  }, [])

  /**
   * Sinkronisasi semua jenis yang spreadsheet-nya sudah ditautkan. Setiap jenis
   * diproses berurutan supaya tidak membanjiri server, dan hasilnya
   * digabungkan menjadi satu pesan untuk petugas.
   */
  const syncStudents = useCallback(async (showFeedback = false) => {
    if (syncingRef.current) return false

    syncingRef.current = true
    setSyncing(true)

    try {
      const results = []

      for (const group of syncGroups) {
        results.push(await syncOneGroup(group))
      }

      const done = results.filter((result) => result.ok)
      const failed = results.filter((result) => !result.ok && !result.skipped)

      if (done.length === 0) {
        // Tidak ada satu pun spreadsheet yang ditautkan/diambil. Hanya gagal
        // yang diberi pesan saat refresh manual agar tidak mengganggu.
        const realFailure = failed[0]
        if (showFeedback) {
          setError(realFailure?.message || 'Belum ada URL CSV spreadsheet. Impor data melalui tombol Impor Spreadsheet terlebih dahulu.')
        }

        return false
      }

      setError('')

      // Waktu sinkron terakhir disimpan per kelompok. Kelompok gabungan
      // (Tendik & Dosen) menulis ke kedua jenisnya karena spreadsheetnya sama.
      setSyncSources((current) => {
        const next = { ...current }
        done.forEach((result) => {
          const keys = result.types?.length
            ? result.types
            : [DEFAULT_BORROWER_TYPE]
          keys.forEach((key) => {
            next[key] = { ...(next[key] || { url: '', webhookUrl: '' }), lastSyncedAt: result.syncedAt }
          })
        })

        return next
      })

      // Muat ulang daftar tanpa efek "Memuat..." agar tabel tidak berkedip
      // setiap siklus sinkronisasi, dan tetap mengikuti filter pencarian aktif.
      await fetchStudents(undefined, true)

      const imported = done.reduce((sum, result) => sum + result.imported, 0)
      const updated = done.reduce((sum, result) => sum + result.updated, 0)
      const unchanged = done.reduce((sum, result) => sum + result.unchanged, 0)
      const skippedRows = done.flatMap((result) => result.errors || [])

      // Baris yang dilewati (mis. email bentrok/tidak valid) tetap
      // diberitahukan agar data yang tidak ikut masuk punya penjelasan.
      if (skippedRows.length) {
        setError(`${skippedRows.length} baris spreadsheet dilewati saat sinkronisasi. ${skippedRows[0]}`)
      } else if (failed.length) {
        // Sebagian jenis gagal: successes-nya tetap dipakai, tapi petugas
        // diberi tahu kelompok mana yang belum masuk.
        setError(`${failed.map((result) => result.label).join(', ')} gagal disinkronkan. ${failed[0]?.message || ''}`.trim())
      }

      if (showFeedback) {
        const detail = [`${imported} baru`, `${updated} diperbarui`]
        if (unchanged) detail.push(`${unchanged} tanpa perubahan`)
        setSuccess(`Sinkronisasi selesai (${done.map((result) => result.label).join(', ')}): ${detail.join(', ')}.`)
      }

      return true
    } catch (requestError) {
      setError(requestError.response?.data?.message || requestError.message || 'Gagal menyinkronkan spreadsheet.')

      return false
    } finally {
      syncingRef.current = false
      setSyncing(false)
    }
  }, [fetchStudents, syncOneGroup, syncGroups])

  // Daftar mahasiswa diambil sekali saat halaman dibuka.
  useEffect(() => { fetchStudents('') }, [fetchStudents])

  // Halaman tabel diganti -> ambil 10 baris halaman itu dari server.
  // Run pertama dilewati karena pemuatan awal sudah ditangani efek di atas.
  // Catatan: jangan berhenti hanya saat `page <= 1` — kembali ke halaman 1
  // juga butuh data halaman 1, bukan sisa data halaman sebelumnya.
  useEffect(() => {
    if (skipPageFetchRef.current) {
      skipPageFetchRef.current = false

      return
    }

    fetchStudents(undefined, false, page)
  }, [page, fetchStudents])

  useEffect(() => {
    const timeout = window.setTimeout(() => {
      // Kata kunci pencarian berubah -> selalu kembali ke halaman 1 supaya
      // hasil pencarian tidak pernah tersembunyi di halaman belakang. Kalau
      // sudah di halaman 1, pengambilannya dilakukan di sini; kalau belum,
      // efek `page` di atas yang mengambil supaya tidak ada permintaan ganda.
      if (pageRef.current === 1) fetchStudents(search, false, 1)
      else setPage(1)
    }, 300)

    return () => window.clearTimeout(timeout)
  }, [search, fetchStudents])

  // Tab jenis diganti -> daftar dimuat ulang dan halaman kembali ke 1.
  // Run pertama dilewati karena pemuatan awal sudah dilakukan efek mount.
  useEffect(() => {
    if (skipTypeFetchRef.current) {
      skipTypeFetchRef.current = false

      return
    }

    // Kalau belum di halaman 1, cukup setPage(1) — efek `page` yang mengambil
    // supaya tidak ada dua permintaan untuk hasil yang sama.
    if (pageRef.current !== 1) setPage(1)
    else fetchStudents(undefined, false, 1)
  }, [typeFilter, fetchStudents])

  // Versi terbaru syncStudents disimpan di ref agar timer otomatis di bawah
  // selalu memakai logika & data terbaru (tanpa membuat interval baru).
  useEffect(() => { syncRef.current = syncStudents }, [syncStudents])

  useEffect(() => {
    let cancelled = false

    // Sumber spreadsheet & waktu sinkron terakhir dimuat untuk kelompok yang
    // ditangani halaman ini, sehingga tab tendik/dosen langsung tahu
    // statusnya walaupun halaman baru dimuat ulang oleh petugas.
    Promise.all(syncTypes.map(async (type) => {
      const value = type.value

      try {
        const response = await api.get('/students/import/source', { params: { type: value } })

        return [value, {
          url: String(response.data?.url || '').trim(),
          lastSyncedAt: String(response.data?.last_synced_at || ''),
          // URL webhook tulis-balik ikut dimuat supaya panel pengaturan Jabatan
          // di modal impor menampilkan URL yang sudah tersimpan.
          webhookUrl: String(response.data?.webhook_url || '').trim(),
        }]
      } catch {
        // URL belum tersimpan atau server belum tersedia.
        return [value, null]
      }
    })).then((entries) => {
      if (cancelled) return

      const loaded = {}
      entries.forEach(([value, data]) => {
        if (data?.url) localStorage.setItem(syncStorageKey(value), data.url)
        loaded[value] = data || { url: '', lastSyncedAt: '', webhookUrl: '' }
      })
      setSyncSources((current) => ({ ...current, ...loaded }))
    })

    return () => { cancelled = true }
  }, [syncTypes])

  // Waktu sinkron terakhir yang paling baru dari kelompok di halaman ini —
  // ditampilkan sebagai ringkasan di bawah tombol Refresh Data.
  const lastSyncedAt = useMemo(() => {
    const times = syncTypes
      .map((type) => syncSources[type.value]?.lastSyncedAt)
      .filter(Boolean)
      .sort()
    const newest = times[times.length - 1]

    return newest ? formatClock(newest) : ''
  }, [syncSources, syncTypes])

  useEffect(() => {
    // Sinkronisasi pertama saat halaman dibuka, lalu diulang setiap
    // SYNC_INTERVAL_SECONDS selama halaman ini terbuka. Perubahan isi
    // spreadsheet (nama, email, telepon, NIP) langsung ikut terbarui.
    syncRef.current?.()

    // Jeda antar siklus disimpan di ref, bukan di state, supaya interval tidak
    // perlu dibuat ulang tiap hitungan detik.
    const countdownRef = { current: SYNC_INTERVAL_SECONDS }
    const interval = window.setInterval(() => {
      if (countdownRef.current > 1) {
        countdownRef.current -= 1

        return
      }

      // Modal impor sedang terbuka: tunda satu siklus agar URL sumber yang
      // sedang diubah tidak tersinkron setengah jalan.
      if (modalOpenRef.current) return

      countdownRef.current = SYNC_INTERVAL_SECONDS
      syncRef.current?.()
    }, 1000)

    return () => window.clearInterval(interval)
  }, [])

  const handleRefresh = async () => {
    setError('')
    setSuccess('')
    await syncStudents(true)
  }

  // Pencarian dipicu tombol "Cari"/Enter: kembali ke halaman 1. Kalau sudah di
  // halaman 1, pengambilan dilakukan langsung; kalau belum, efek `page` yang
  // mengambil supaya tidak ada dua permintaan untuk hasil yang sama.
  const runSearch = () => {
    if (pageRef.current === 1) fetchStudents(search, false, 1)
    else setPage(1)
  }

  const handleImported = (result) => {
    setError('')
    if (result?.synced_at) {
      const value = normalizeBorrowerType(result.type)
      setSyncSources((current) => ({
        ...current,
        [value]: { ...(current[value] || { url: '' }), lastSyncedAt: result.synced_at },
      }))
    }
    fetchStudents()
  }

  const handleSubmit = async (event) => {
    event.preventDefault()
    setError('')
    setSuccess('')
    setSubmitting(true)
    try {
      if (editingStudent) {
        const response = await api.put(`/students/${editingStudent.id}`, form)
        setSuccess(response.data?.message || `Data ${borrowerTypeLabel(form.type).toLowerCase()} berhasil diperbarui.`)
        // Pesan backend sudah menyebut bila jabatan gagal dikirim ke
        // spreadsheet. Tampilkan sebagai peringatan agar petugas tahu nilainya
        // aman di aplikasi tapi belum sampai ke spreadsheet.
        if (response.data?.spreadsheet && !response.data.spreadsheet.ok && !response.data.spreadsheet.skipped) {
          setError(response.data.spreadsheet.message)
        }
      } else {
        await api.post('/students', form)
        setSuccess(`Data ${borrowerTypeLabel(form.type).toLowerCase()} berhasil ditambahkan.`)
      }
      setForm({ ...emptyForm, type: isLocked ? lockedType : form.type })
      setEditingStudent(null)
      fetchStudents()
    } catch (requestError) {
      const data = requestError.response?.data
      setError(data?.errors ? Object.values(data.errors).flat().join(', ') : data?.message || 'Data peminjam gagal disimpan.')
    } finally {
      setSubmitting(false)
    }
  }

  const handleEdit = (student) => {
    setEditingStudent(student)
    setForm({
      student_id: student.student_id,
      name: student.name,
      type: student.type || DEFAULT_BORROWER_TYPE,
      role: student.role || '',
      position: student.position || '',
      email: student.email,
      phone: student.phone || '',
    })
    setError('')
    setSuccess('')
  }

  const cancelEdit = () => {
    setEditingStudent(null)
    setForm({ ...emptyForm, type: isLocked ? lockedType : form.type })
  }

  const handleDelete = async (student) => {
    if (!window.confirm(`Hapus data peminjam "${student.name}"?`)) return
    setError('')
    try {
      await api.delete(`/students/${student.id}`)
      setSuccess('Data peminjam berhasil dihapus.')
      // Baris terakhir pada halaman ini dihapus: mundur satu halaman supaya
      // tabel tidak tampil kosong menetap.
      if (students.length <= 1 && page > 1) setPage((current) => current - 1)
      else fetchStudents()
    } catch (requestError) {
      setError(requestError.response?.data?.message || 'Data peminjam gagal dihapus.')
    }
  }

  const updateForm = (event) => {
    const { name, value } = event.target

    setForm((current) => ({
      ...current,
      [name]: value,
      ...(name === 'position' && ['tendik', 'dosen'].includes(current.type)
        ? { type: employeeTypeFromPosition(value) }
        : {}),
    }))
  }

  // Jenis yang spreadsheet-nya sudah ditautkan — ditampilkan sebagai ringkasan
  // supaya petugas tahu kelompok mana saja yang ikut tersinkron otomatis.
  const linkedTypes = syncTypes
    .filter((type) => syncSources[type.value]?.url)
    .map((type) => type.value)

  // Tab jenis: disembunyikan pada halaman yang jenisnya terkunci (mahasiswa),
  // karena di sana hanya ada satu kelompok yang relevan.
  const tabs = isLocked
    ? []
    : [{ value: '', label: 'Semua' }, ...BORROWER_TYPES.filter((type) => scope.includes(type.value))]

  // Form tambah hanya boleh memakai jenis yang ditangani halaman ini.
  const formTypes = BORROWER_TYPES.filter((type) => scope.includes(type.value))

  return (
    <div className="mx-auto max-w-5xl">
      <div className="mb-8 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">{title}</h1>
          <p className="mt-1 text-slate-500">{subtitle}</p>
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
            {linkedTypes.length > 0 && (
              <span className="block text-slate-400">
                Spreadsheet: {linkedTypes.map((type) => borrowerTypeLabel(type)).join(', ')}
              </span>
            )}
            {lastSyncedAt && (
              <span className="block text-slate-400">Sinkron terakhir {lastSyncedAt}</span>
            )}
          </span>
        </div>
      </div>

      <ImportStudentsModal
        open={showImportModal}
        onClose={() => setShowImportModal(false)}
        onImported={handleImported}
        initialType={typeFilter || lockedType || form.type}
        allowedTypes={formTypes.map((type) => type.value)}
        sources={syncSources}
        onSourcesChange={setSyncSources}
      />

      {error && <div className="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>}
      {success && <div className="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{success}</div>}

      {tabs.length > 0 && (
        <div className="mb-4 flex flex-wrap gap-2">
          {tabs.map((tab) => {
            const active = typeFilter === tab.value
            // Tab "" (Semua) memakai jumlah kelompok halaman ini saja, tab
            // jenis tertentu memakai jumlah jenis tersebut.
            const count = tab.value === '' ? (counts.scope ?? 0) : (counts[tab.value] ?? 0)

            return (
              <button
                key={tab.value || 'all'}
                type="button"
                onClick={() => setTypeFilter(tab.value)}
                className={`inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-sm font-medium transition ${
                  active
                    ? 'border-cyan-600 bg-cyan-600 text-white'
                    : 'border-slate-300 bg-white text-slate-600 hover:bg-slate-50'
                }`}
              >
                {tab.label}
                <span className={`rounded-full px-1.5 text-xs ${active ? 'bg-white/20' : 'bg-slate-100 text-slate-500'}`}>{count}</span>
              </button>
            )
          })}
        </div>
      )}

      <form onSubmit={handleSubmit} className="mb-6 grid grid-cols-1 gap-4 rounded-xl border border-slate-200 bg-white p-6 md:grid-cols-2">
        {formTypes.length > 1 && (
          <label className="text-sm font-medium text-slate-700">
            Jenis Peminjam *
            <select
              required
              name="type"
              value={form.type}
              onChange={updateForm}
              className="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500"
            >
              {formTypes.map((type) => (
                <option key={type.value} value={type.value}>{type.label}</option>
              ))}
            </select>
          </label>
        )}
        {['tendik', 'dosen'].includes(form.type) && (
          <label className="text-sm font-medium text-slate-700">
            Role (opsional)
            <input name="role" value={form.role} onChange={updateForm} placeholder="Contoh: Dosen, Tendik, Rumah Tangga" className="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500" />
          </label>
        )}
        <label className="text-sm font-medium text-slate-700">
          {identityLabel(form.type)}
          {['tendik', 'dosen'].includes(form.type) ? ' (opsional)' : ' *'}
          <input required={!['tendik', 'dosen'].includes(form.type)} name="student_id" value={form.student_id || ''} onChange={updateForm} placeholder={form.type === 'mahasiswa' ? 'Contoh: 2211082001' : 'Kosongkan jika tidak ada'} className="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500" />
        </label>
        <label className="text-sm font-medium text-slate-700">
          Nama lengkap *
          <input required name="name" value={form.name} onChange={updateForm} placeholder="Nama lengkap peminjam" className="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500" />
        </label>
        <label className="text-sm font-medium text-slate-700">
          Jabatan / Unit Kerja
          <input name="position" value={form.position} onChange={updateForm} placeholder={form.type === 'mahasiswa' ? 'Program studi (opsional)' : 'Tugas atau unit kerja'} className="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500" />
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
            {editingStudent ? 'Simpan Perubahan' : `Tambah ${borrowerTypeLabel(form.type)}`}
          </button>
          {editingStudent && <button type="button" onClick={cancelEdit} className="rounded-lg border border-slate-300 px-4 py-2.5 font-medium text-slate-600 hover:bg-slate-50">Batal</button>}
        </div>
        {addHint && <p className="text-xs text-slate-500 md:col-span-2">{addHint}</p>}
      </form>

      <div className="mb-4 flex flex-col gap-2 sm:flex-row">
        <div className="relative flex-1">
          <Search className="absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
          <input value={search} onChange={(event) => setSearch(event.target.value)} onKeyDown={(event) => { if (event.key === 'Enter') runSearch() }} placeholder="Cari NIM/NIP, nama, role, unit kerja, atau email..." className="w-full rounded-lg border border-slate-300 py-2.5 pl-10 pr-4 outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500" />
        </div>
        <button type="button" onClick={runSearch} className="inline-flex items-center justify-center gap-2 rounded-lg bg-slate-800 px-5 py-2.5 font-medium text-white hover:bg-slate-900"><Search className="h-4 w-4" />Cari</button>
      </div>

      <div className="overflow-hidden rounded-xl border border-slate-200 bg-white">
        {loading ? <p className="p-8 text-center text-slate-500">Memuat data peminjam...</p> : students.length === 0 ? <div className="p-8 text-center text-slate-500"><UserRound className="mx-auto mb-3 h-10 w-10 text-slate-300" />{search ? 'Peminjam tidak ditemukan.' : 'Belum ada data peminjam.'}</div> : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="bg-slate-50"><tr><th className="w-14 px-5 py-3 text-center font-medium text-slate-500">No.</th><th className="px-5 py-3 font-medium text-slate-500">Kategori / Role</th><th className="px-5 py-3 font-medium text-slate-500">NIM / NIP</th><th className="px-5 py-3 font-medium text-slate-500">Nama</th><th className="px-5 py-3 font-medium text-slate-500">Kontak</th><th className="px-5 py-3 text-right font-medium text-slate-500">Aksi</th></tr></thead>
              <tbody className="divide-y divide-slate-200">{students.map((student, index) => <tr key={student.id}><td className="px-5 py-3 text-center text-slate-400">{(page - 1) * PER_PAGE + index + 1}</td><td className="px-5 py-3"><span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset ${borrowerTypeBadgeClass(student.type)}`}>{borrowerTypeLabel(student.type)}</span>{student.role && <div className="mt-1 text-xs text-slate-500">{student.role}</div>}</td><td className="px-5 py-3 font-mono text-slate-700">{student.student_id || '—'}</td><td className="px-5 py-3 font-medium text-slate-900">{student.name}{student.position && <div className="mt-0.5 text-xs font-normal text-slate-500">{student.position}</div>}</td><td className="px-5 py-3 text-slate-600"><div className="flex items-center gap-1.5"><Mail className="h-3.5 w-3.5 text-slate-400" />{student.email}</div>{student.phone && <div className="mt-1 flex items-center gap-1.5 text-xs"><Phone className="h-3.5 w-3.5 text-slate-400" />{student.phone}</div>}</td><td className="px-5 py-3 text-right"><div className="inline-flex gap-2"><button type="button" onClick={() => handleEdit(student)} className="rounded-lg p-1.5 text-slate-500 hover:bg-cyan-50 hover:text-cyan-600" aria-label={`Edit ${student.name}`}><Pencil className="h-4 w-4" /></button><button type="button" onClick={() => handleDelete(student)} className="rounded-lg p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600" aria-label={`Hapus ${student.name}`}><Trash2 className="h-4 w-4" /></button></div></td></tr>)}</tbody>
            </table>
          </div>
        )}
      </div>

      <TablePagination
        page={page}
        lastPage={lastPage}
        onPageChange={setPage}
        total={total}
        perPage={PER_PAGE}
        className="mt-4"
      />
    </div>
  )
}
