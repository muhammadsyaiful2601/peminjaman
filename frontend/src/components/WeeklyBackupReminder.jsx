import { useCallback, useEffect, useState } from 'react'
import { useLocation, useNavigate } from 'react-router-dom'
import { CalendarClock, DatabaseBackup, KeyRound, Loader2, ShieldCheck, X } from 'lucide-react'
import api from '../api/axios'
import { useAuth } from '../context/AuthContext'
import { downloadBlob } from '../utils/downloadBlob'

/**
 * Pengingat backup mingguan.
 *
 * Backup merupakan rutin berkala: begitu aplikasi dibuka dan backup terakhir
 * sudah lebih dari satu minggu, dialog ini muncul dan menawarkan backup
 * lengkap (database + foto). Pengguna bebas memilih backup sekarang,
 * menundanya ("nanti"), atau membuka Pengaturan Sistem. Setelah backup dibuat
 * atau penundaan dipilih, backend tidak menawarkannya lagi sampai siklus
 * berikutnya sehingga tidak mengganggu pekerjaan.
 */

function formatDateTime(value) {
  if (!value) return '-'
  try {
    return new Date(value).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' })
  } catch {
    return value
  }
}

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

function WeeklyBackupReminder() {
  const { user } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const [open, setOpen] = useState(false)
  const [reminder, setReminder] = useState(null)
  const [photos, setPhotos] = useState(null)
  const [password, setPassword] = useState('')
  const [busy, setBusy] = useState(false)
  const [feedback, setFeedback] = useState(null) // { ok, message }

  // Hanya admin yang boleh membuat backup, jadi pengingat tidak perlu
  // ditunjukkan kepada akun lain.
  const isAdmin = user?.role === 'admin'

  const checkReminder = useCallback(async () => {
    if (!isAdmin) return

    try {
      const { data } = await api.get('/backups/status')
      if (data?.reminder?.due) {
        setReminder(data.reminder)
        setPhotos(data.photos || null)
        setFeedback(null)
        setPassword('')
        setOpen(true)
      }
    } catch {
      // Status backup tidak terbaca (mis. server sedang sibuk): jangan
      // mengganggu pengguna, backup tetap bisa dilakukan lewat Pengaturan.
    }
  }, [isAdmin])

  useEffect(() => {
    checkReminder()
  }, [checkReminder])

  const close = () => {
    setOpen(false)
    setPassword('')
    setFeedback(null)
  }

  const handleBackup = async (event) => {
    event.preventDefault()
    if (!password || busy) return

    setBusy(true)
    setFeedback(null)

    try {
      const response = await api.post('/backups/full', { password }, { responseType: 'blob', timeout: 15 * 60 * 1000 })
      const result = await downloadBlob(response.data, 'backup-lengkap.zip')

      if (result && !result.ok && !result.canceled) {
        setFeedback({ ok: false, message: result.message || 'Backup gagal disimpan.' })
        return
      }

      setFeedback({ ok: true, message: 'Backup lengkap siap disimpan. Pengingat berikutnya muncul dalam 7 hari.' })
      setPassword('')
      setReminder((current) => (current ? { ...current, due: false, last_backup_at: new Date().toISOString() } : current))
      window.setTimeout(close, 4000)
    } catch (err) {
      let data = err.response?.data
      if (data instanceof Blob) {
        try {
          data = JSON.parse(await data.text())
        } catch {
          data = null
        }
      }
      setFeedback({ ok: false, message: data?.message || 'Backup gagal dibuat. Silakan coba lagi.' })
    } finally {
      setBusy(false)
    }
  }

  // Penundaan: pengguna memilih tidak backup sekarang. Backend menyimpan
  // batas waktunya sehingga dialog ini tidak muncul lagi pada siklus ini.
  const snooze = async () => {
    setBusy(true)
    try {
      await api.post('/backups/reminder/snooze', {})
    } catch {
      // Gagal menyimpan penundaan tidak boleh menahan pengguna: dialog ditutup.
    } finally {
      setBusy(false)
      close()
    }
  }

  // Pengguna memilih mengerjakan lewat halaman Pengaturan Sistem. Dialog ditutup
  // tanpa menunda agar backup bisa langsung dilakukan di sana.
  const openSettings = () => {
    close()
    navigate('/settings')
  }

  if (!open || !reminder) return null

  const since = reminder.last_backup_at
    ? `Backup terakhir: ${formatDateTime(reminder.last_backup_at)}`
    : 'Belum pernah membuat backup.'
  const intro = reminder.last_backup_at
    ? `Sudah ${reminder.days_since_backup} hari sejak backup terakhir.`
    : 'Belum ada backup sama sekali.'

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4">
      <div className="w-full max-w-lg rounded-2xl border border-slate-200 bg-white shadow-xl">
        <div className="flex items-start justify-between gap-4 border-b border-slate-100 px-6 pb-4 pt-5">
          <div className="flex items-start gap-3">
            <span className="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-cyan-50 text-cyan-600">
              <CalendarClock className="h-5 w-5" />
            </span>
            <div>
              <h2 className="text-base font-bold leading-tight text-slate-900">Pengingat Backup Mingguan</h2>
              <p className="mt-0.5 text-xs text-slate-500">{since}</p>
            </div>
          </div>
          <button type="button" onClick={snooze} disabled={busy} className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 disabled:opacity-50" aria-label="Tutup">
            <X className="h-5 w-5" />
          </button>
        </div>

        <div className="px-6 pt-4">
          <div className="rounded-xl border border-cyan-100 bg-cyan-50/60 p-4 text-sm text-slate-700">
            <p>
              {intro} Backup lengkap (database + seluruh foto) melindungi data peminjaman, dan masih bisa dipulihkan
              kembali bila terjadi kerusakan.
            </p>
            {photos && (
              <p className="mt-2 text-xs text-slate-500">
                Backup akan memuat {photos.files} berkas foto/gambar ({formatBytes(photos.bytes)}).
              </p>
            )}
          </div>

          <form onSubmit={handleBackup} className="mt-4">
            <label className="text-sm font-medium text-slate-700">
              Password admin
              <div className="relative mt-1.5">
                <KeyRound className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                  autoFocus
                  type="password"
                  value={password}
                  onChange={(event) => setPassword(event.target.value)}
                  placeholder="Masukkan password admin"
                  className="w-full rounded-lg border border-slate-300 py-2.5 pl-9 pr-3 text-sm outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-100"
                />
              </div>
            </label>

            {feedback && (
              <p className={`mt-3 rounded-lg px-3 py-2 text-sm ${feedback.ok ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'}`}>
                {feedback.message}
              </p>
            )}

            <div className="mt-5 flex flex-wrap justify-end gap-2">
              {location.pathname !== '/settings' && (
                <button type="button" onClick={openSettings} disabled={busy} className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 disabled:opacity-50">
                  Buka Pengaturan
                </button>
              )}
              <button type="button" onClick={snooze} disabled={busy} className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 disabled:opacity-50">
                Nanti, Minggu Depan
              </button>
              <button type="submit" disabled={busy || !password} className="inline-flex items-center gap-2 rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700 disabled:opacity-50">
                {busy ? <Loader2 className="h-4 w-4 animate-spin" /> : <DatabaseBackup className="h-4 w-4" />}
                {busy ? 'Menyiapkan...' : 'Backup Sekarang'}
              </button>
            </div>
          </form>

          <p className="mt-4 flex items-start gap-2 text-xs text-slate-500">
            <ShieldCheck className="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" />
            Dialog ini muncul otomatis di awal minggu saat aplikasi dibuka, dan hanya bila backup belum dibuat.
          </p>
        </div>
      </div>
    </div>
  )
}

export default WeeklyBackupReminder

