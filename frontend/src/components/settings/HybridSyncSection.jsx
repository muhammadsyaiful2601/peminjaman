import { useCallback, useEffect, useState } from 'react'
import { Database, Loader2, RefreshCw, Server, UploadCloud } from 'lucide-react'
import api from '../../api/axios'

/**
 * Seksi "Hosting & Sinkronisasi" di Pengaturan Sistem (khusus desktop).
 * Mode hybrid: SQLite lokal tetap jadi sumber utama agar aplikasi jalan
 * offline, lalu data dicerminkan ke MySQL hosting dan disinkronkan dua arah.
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

export default function HybridSyncSection() {
  const [form, setForm] = useState(EMPTY_FORM)
  const [status, setStatus] = useState(null)
  const [busy, setBusy] = useState(null) // 'save' | 'migrate' | 'sync' | 'toggle' | null
  const [feedback, setFeedback] = useState(null) // { ok, message }

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
    loadStatus()
  }, [loadStatus])

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

  const handleSave = () => runAction('save', () => api.post('/hybrid/config', form))
  const handleTest = () => runAction('save', () => api.post('/hybrid/test', form))
  const handleMigrate = () => runAction('migrate', () => api.post('/hybrid/migrate'))
  const handleSync = () => runAction('sync', () => api.post('/hybrid/sync'))
  const handleToggle = () =>
    runAction('toggle', () => api.post('/hybrid/toggle', { enabled: !(status?.enabled ?? false) }))

  return (
    <div className="space-y-3">
      <div className="rounded-xl border border-slate-200 bg-slate-50 p-4 text-xs text-slate-600">
        <div className="flex items-center justify-between">
          <span>Status sinkronisasi otomatis</span>
          <span className={`font-semibold ${status?.enabled ? 'text-emerald-600' : 'text-slate-400'}`}>
            {status?.enabled ? `Aktif (tiap ${status.interval_days} hari)` : 'Nonaktif'}
          </span>
        </div>
        <div className="mt-1 flex items-center justify-between">
          <span>Terakhir disinkronkan</span>
          <span className="font-semibold text-slate-700">{formatDateTime(status?.last_sync_at)}</span>
        </div>
        {status?.last_error && (
          <div className="mt-1 flex items-start justify-between gap-3">
            <span className="shrink-0">Pesan error</span>
            <span className="text-right font-semibold text-rose-600">{status.last_error}</span>
          </div>
        )}
      </div>

      <div className="rounded-xl border border-slate-200 p-4">
        <p className="text-sm font-semibold text-slate-700">Host / Link MySQL Hosting</p>
        <div className="mt-3 grid gap-3 sm:grid-cols-2">
          <label className="text-xs font-medium text-slate-600">
            Host / Link
            <input
              value={form.host}
              onChange={setField('host')}
              placeholder="localhost"
              className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-cyan-500 focus:outline-none"
            />
          </label>
          <label className="text-xs font-medium text-slate-600">
            Port
            <input
              value={form.port}
              onChange={setField('port')}
              placeholder="3306"
              className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-cyan-500 focus:outline-none"
            />
          </label>
          <label className="text-xs font-medium text-slate-600">
            Nama Database
            <input
              value={form.database}
              onChange={setField('database')}
              placeholder="peminjaman"
              className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-cyan-500 focus:outline-none"
            />
          </label>
          <label className="text-xs font-medium text-slate-600">
            Username Database
            <input
              value={form.username}
              onChange={setField('username')}
              className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-cyan-500 focus:outline-none"
            />
          </label>
          <label className="text-xs font-medium text-slate-600 sm:col-span-2">
            Password Database
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
      </div>

      <div className="flex flex-wrap gap-2">
        <button
          type="button"
          disabled={busy !== null}
          onClick={handleSave}
          className="inline-flex items-center gap-1.5 rounded-lg bg-cyan-600 px-3.5 py-2 text-sm font-semibold text-white hover:bg-cyan-700 disabled:opacity-50"
        >
          {busy === 'save' ? <Loader2 className="h-4 w-4 animate-spin" /> : <Server className="h-4 w-4" />}
          Simpan Konfigurasi
        </button>
        <button
          type="button"
          disabled={busy !== null}
          onClick={handleTest}
          className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3.5 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50"
        >
          <Server className="h-4 w-4" />
          Tes Koneksi
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

      {feedback && (
        <div
          className={`rounded-lg border p-3 text-xs break-words ${
            feedback.ok ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-700'
          }`}
        >
          {feedback.message}
        </div>
      )}

      <p className="text-[11px] leading-relaxed text-slate-400">
        Data utama tetap tersimpan di komputer ini (SQLite) agar aplikasi tetap jalan offline. Setelah migrasi, data
        dicerminkan ke MySQL hosting dan disinkronkan dua arah otomatis setiap minggu (dapat dipicu manual kapan saja).
        Bagian ini hanya tersedia di aplikasi desktop.
      </p>
    </div>
  )
}