import { useCallback, useEffect, useState } from 'react'
import { CloudDownload, Database, Loader2, RefreshCw, Server, Settings, UploadCloud, X } from 'lucide-react'
import api from '../api/axios'

/**
 * Mode Hybrid — tombol gear di sudut kanan bawah (khusus aplikasi desktop).
 * Membuka popup pengaturan migrasi & sinkronisasi data ke MySQL hosting:
 * SQLite lokal tetap dipakai saat offline, data dicerminkan ke hosting
 * dan disinkronkan dua arah setiap minggu secara otomatis.
 */

const EMPTY_FORM = { host: '', port: '3306', database: '', username: '', password: '' }

function formatDateTime(value) {
  if (!value) return '-'
  try {
    return new Date(value).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' })
  } catch {
    return value
  }
}

function HybridSettings() {
  const isDesktop = Boolean(window.desktop?.isDesktop)
  const [open, setOpen] = useState(false)
  const [form, setForm] = useState(EMPTY_FORM)
  const [status, setStatus] = useState(null)
  const [busy, setBusy] = useState(null) // 'save' | 'test' | 'migrate' | 'sync' | null
  const [feedback, setFeedback] = useState(null) // { ok, message }
  const [updateState, setUpdateState] = useState(null) // state pembaruan (menu gear)
  const [updateBusy, setUpdateBusy] = useState(null) // 'check' | 'download' | null

  const loadStatus = useCallback(async () => {
    try {
      const { data } = await api.get('/hybrid/status')
      setStatus(data)
      setForm((prev) => ({
        ...prev,
        host: data.host || '',
        port: String(data.port || '3306'),
        database: data.database || '',
        username: data.username || '',
        password: '',
      }))
    } catch {
      setStatus(null)
    }
  }, [])

  useEffect(() => {
    if (isDesktop && open) {
      loadStatus()
    }
  }, [isDesktop, open, loadStatus])

  // Menu gear juga menampilkan state pembaruan aplikasi (periksa versi di
  // latar belakang oleh main process + push realtime saat pengunduh manual).
  useEffect(() => {
    if (!isDesktop) return
    const desktop = window.desktop
    if (desktop?.getUpdateState) {
      desktop
        .getUpdateState()
        .then(setUpdateState)
        .catch(() => setUpdateState({ state: 'error', message: 'Tak dapat membaca state pembaruan.' }))
    }
    if (desktop?.onUpdateState) {
      return desktop.onUpdateState(setUpdateState)
    }
    return undefined
  }, [isDesktop])

  if (!isDesktop) {
    return null
  }

  const setField = (key) => (event) => setForm((prev) => ({ ...prev, [key]: event.target.value }))

  const runAction = async (name, action) => {
    setBusy(name)
    setFeedback(null)
    try {
      const { data } = await action()
      setFeedback({ ok: Boolean(data.ok ?? data.test_ok), message: data.message || 'Selesai.' })
      await loadStatus()
    } catch (err) {
      setFeedback({
        ok: false,
        message: err.response?.data?.message || 'Gagal menghubungi server lokal. Pastikan aplikasi berjalan.',
      })
    } finally {
      setBusy(null)
    }
  }

  const handleSave = () =>
    runAction('save', () =>
      api.post('/hybrid/config', {
        host: form.host,
        port: Number(form.port) || 3306,
        database: form.database,
        username: form.username,
        password: form.password || undefined,
      }),
    )

  const handleTest = () =>
    runAction('test', () =>
      api.post('/hybrid/test', {
        host: form.host,
        port: Number(form.port) || 3306,
        database: form.database,
        username: form.username,
        password: form.password || undefined,
      }),
    )

  const handleMigrate = () => runAction('migrate', () => api.post('/hybrid/migrate'))
  const handleSync = () => runAction('sync', () => api.post('/hybrid/sync'))

  const handleToggle = () =>
    runAction('toggle', () => api.post('/hybrid/toggle', { enabled: !(status?.enabled ?? false) }))

  const handleCheckUpdate = async () => {
    setUpdateBusy('check')
    try {
      await window.desktop.checkForUpdates()
    } finally {
      setUpdateBusy(null)
    }
  }

  const handleDownloadUpdate = async () => {
    setUpdateBusy('download')
    try {
      await window.desktop.downloadUpdate()
    } finally {
      setUpdateBusy(null)
    }
  }

  return (
    <>
      {/* Tombol gear mengambang di sudut kiri bawah */}
      <button
        type="button"
        onClick={() => setOpen(true)}
        title="Pengaturan Hosting & Sinkronisasi (mode hybrid)"
        className="fixed bottom-5 left-5 z-50 inline-flex h-12 w-12 items-center justify-center rounded-full
                   bg-white border border-slate-200 shadow-lg text-slate-500 hover:text-cyan-600
                   hover:border-cyan-300 hover:shadow-xl transition"
      >
        <Settings className="h-5 w-5" />
      </button>

      {open && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
          <div className="w-full max-w-lg bg-white rounded-2xl shadow-xl border border-slate-200 max-h-[90vh] overflow-y-auto">
            <div className="flex items-center justify-between px-6 pt-5 pb-4 border-b border-slate-100">
              <div className="flex items-center gap-2">
                <span className="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-cyan-50 text-cyan-600">
                  <Database className="h-5 w-5" />
                </span>
                <div>
                  <h2 className="text-base font-bold text-slate-900 leading-tight">Hosting & Sinkronisasi</h2>
                  <p className="text-xs text-slate-500">Mode hybrid — cermin data ke MySQL hosting</p>
                </div>
              </div>
              <button
                type="button"
                onClick={() => setOpen(false)}
                className="p-1.5 rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-600"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            {/* PEMBARUAN APLIKASI (menu baru di gear), stil Play Store */}
            <div className="px-6 pt-4">
              <div className="rounded-xl border border-slate-200 p-3 text-xs text-slate-600">
                <div className="flex items-center justify-between">
                  <span className="font-semibold text-slate-700 flex items-center gap-1.5">
                    <CloudDownload className="h-4 w-4" />
                    Pembaruan Aplikasi
                  </span>
                  {updateState?.state === 'checking' || updateBusy === 'check' ? (
                    <Loader2 className="h-4 w-4 animate-spin text-slate-400" />
                  ) : (
                    <button
                      type="button"
                      onClick={handleCheckUpdate}
                      className="inline-flex items-center gap-1 text-cyan-600 hover:text-cyan-700"
                    >
                      <RefreshCw className="h-3.5 w-3.5" />
                      Periksa
                    </button>
                  )}
                </div>

                {updateState && updateState.state !== 'idle' && (
                  <div className="mt-2 space-y-1.5">
                    <div className="flex items-center justify-between">
                      <span>Versi sekarang</span>
                      <span className="font-medium">{updateState.currentVersion || '-'}</span>
                    </div>

                    {updateState.state === 'checking' && (
                      <p className="text-slate-400">Periksa pembaruan di GitHub…</p>
                    )}

                    {updateState.state === 'up-to-date' && (
                      <p className="text-emerald-600">Aplikasi sudah versi terbaru.</p>
                    )}

                    {updateState.state === 'available' && (
                      <div className="rounded-lg border border-cyan-200 bg-cyan-50 p-2">
                        <p className="text-cyan-800 font-medium">Versi baru {updateState.version || ''} tersedia.</p>
                        <button
                          type="button"
                          disabled={updateBusy !== null}
                          onClick={handleDownloadUpdate}
                          className="mt-1.5 inline-flex items-center gap-1.5 rounded-lg bg-cyan-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-cyan-700 disabled:opacity-50"
                        >
                          {updateBusy === 'download' ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <CloudDownload className="h-3.5 w-3.5" />}
                          Pengunduh &amp; Instal
                        </button>
                      </div>
                    )}

                    {updateState.state === 'downloading' && (
                      <div>
                        <div className="flex items-center justify-between">
                          <span className="flex items-center gap-1.5">
                            <Loader2 className="h-3.5 w-3.5 animate-spin" />
                            Pengunduh versi {updateState.version || ''}…
                          </span>
                          <span className="font-medium">{updateState.percent || 0}%</span>
                        </div>
                        <div className="mt-1.5 h-1.5 rounded-full bg-slate-200 overflow-hidden">
                          <div
                            className="h-full rounded-full bg-cyan-500 transition-all"
                            style={{ width: `${updateState.percent || 0}%` }}
                          />
                        </div>
                      </div>
                    )}

                    {updateState.state === 'ready' && (
                      <div className="rounded-lg border border-emerald-200 bg-emerald-50 p-2">
                        <p className="text-emerald-700 font-medium">
                          Pengunduhan selesai — versi {updateState.version || ''}.
                        </p>
                        <p className="text-emerald-600 mt-1">
                          Aplikasi akan dimulai ulang otomatis beberapa detik lagi untuk memasang pembaruan. Data Anda tetap aman.
                        </p>
                        <button
                          type="button"
                          onClick={() => window.desktop.installUpdate()}
                          className="mt-1.5 inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700"
                        >
                          <Settings className="h-3.5 w-3.5" />
                          Restart &amp; Instal Sekarang
                        </button>
                      </div>
                    )}

                    {updateState.state === 'error' && (
                      <p className="text-rose-600 break-words">Gagal: {updateState.message}</p>
                    )}
                  </div>
                )}
              </div>
            </div>

            {/* STATUS SINKRONISASI */}
            <div className="px-6 pt-4">
              <div className="rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs text-slate-600 space-y-1">
                <div className="flex items-center justify-between">
                  <span>Status sinkronisasi otomatis</span>
                  <span className={`font-semibold ${status?.enabled ? 'text-emerald-600' : 'text-slate-400'}`}>
                    {status?.enabled ? `Aktif (tiap ${status.interval_days} hari)` : 'Nonaktif'}
                  </span>
                </div>
                <div className="flex items-center justify-between">
                  <span>Terakhir disinkronkan</span>
                  <span className="font-medium">{formatDateTime(status?.last_sync_at)}</span>
                </div>
                {status?.last_sync_message && (
                  <p className={`pt-1 break-words ${status.last_sync_ok ? 'text-emerald-600' : 'text-rose-600'}`}>
                    {status.last_sync_message}
                  </p>
                )}
              </div>
            </div>

            {/* FORM KONEKSI */}
            <div className="px-6 py-4 space-y-3">
              <div className="grid grid-cols-3 gap-3">
                <label className="col-span-2 block">
                  <span className="text-xs font-medium text-slate-600">Host / Link MySQL Hosting</span>
                  <input
                    value={form.host}
                    onChange={setField('host')}
                    placeholder="mis. sql123.hostinger.com"
                    className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-cyan-500 focus:outline-none"
                  />
                </label>
                <label className="block">
                  <span className="text-xs font-medium text-slate-600">Port</span>
                  <input
                    value={form.port}
                    onChange={setField('port')}
                    placeholder="3306"
                    className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-cyan-500 focus:outline-none"
                  />
                </label>
              </div>
              <div className="grid grid-cols-2 gap-3">
                <label className="block">
                  <span className="text-xs font-medium text-slate-600">Nama Database</span>
                  <input
                    value={form.database}
                    onChange={setField('database')}
                    placeholder="mis. u123_peminjaman"
                    className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-cyan-500 focus:outline-none"
                  />
                </label>
                <label className="block">
                  <span className="text-xs font-medium text-slate-600">Username Database</span>
                  <input
                    value={form.username}
                    onChange={setField('username')}
                    placeholder="mis. u123_admin"
                    autoComplete="off"
                    className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-cyan-500 focus:outline-none"
                  />
                </label>
              </div>
              <label className="block">
                <span className="text-xs font-medium text-slate-600">
                  Password Database{' '}
                  {status?.has_password && <span className="text-slate-400">(kosongkan bila tidak diubah)</span>}
                </span>
                <input
                  type="password"
                  value={form.password}
                  onChange={setField('password')}
                  placeholder="••••••••"
                  autoComplete="new-password"
                  className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-cyan-500 focus:outline-none"
                />
              </label>
            </div>

            {/* TOMBOL AKSI */}
            <div className="px-6 pb-2 flex flex-wrap gap-2">
              <button
                type="button"
                disabled={busy !== null}
                onClick={handleSave}
                className="inline-flex items-center gap-1.5 rounded-lg bg-cyan-600 px-3.5 py-2 text-sm font-semibold text-white hover:bg-cyan-700 disabled:opacity-50"
              >
                {busy === 'save' ? <Loader2 className="h-4 w-4 animate-spin" /> : <Server className="h-4 w-4" />}
                Simpan & Tes Koneksi
              </button>
              <button
                type="button"
                disabled={busy !== null}
                onClick={handleMigrate}
                className="inline-flex items-center gap-1.5 rounded-lg bg-slate-800 px-3.5 py-2 text-sm font-semibold text-white hover:bg-slate-900 disabled:opacity-50"
              >
                {busy === 'migrate' ? <Loader2 className="h-4 w-4 animate-spin" /> : <UploadCloud className="h-4 w-4" />}
                Migrasi Data ke Hosting
              </button>
              <button
                type="button"
                disabled={busy !== null || !status?.configured}
                onClick={handleSync}
                className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3.5 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50"
              >
                {busy === 'sync' ? <Loader2 className="h-4 w-4 animate-spin" /> : <RefreshCw className="h-4 w-4" />}
                Sinkron Sekarang
              </button>
              <button
                type="button"
                disabled={busy !== null || !status?.configured}
                onClick={handleToggle}
                className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3.5 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50"
              >
                <Database className="h-4 w-4" />
                {status?.enabled ? 'Matikan Otomatis' : 'Aktifkan Otomatis'}
              </button>
            </div>

            {/* FEEDBACK */}
            {feedback && (
              <div className="mx-6 my-3">
                <div
                  className={`rounded-lg border p-3 text-xs break-words ${
                    feedback.ok
                      ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                      : 'border-rose-200 bg-rose-50 text-rose-700'
                  }`}
                >
                  {feedback.message}
                </div>
              </div>
            )}

            <div className="px-6 py-4 border-t border-slate-100">
              <p className="text-[11px] leading-relaxed text-slate-400">
                Data utama tetap tersimpan di komputer ini (SQLite) agar aplikasi tetap jalan offline. Setelah migrasi,
                data dicerminkan ke MySQL hosting dan disinkronkan dua arah otomatis setiap minggu (dapat dipicu manual
                kapan saja). Tombol gear ini hanya ada di aplikasi desktop.
              </p>
            </div>
          </div>
        </div>
      )}
    </>
  )
}

export default HybridSettings