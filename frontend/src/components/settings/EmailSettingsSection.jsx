import { useEffect, useState } from 'react'
import { Loader2, Mail, Save, Send } from 'lucide-react'

/**
 * Seksi "Email (SMTP)" di Pengaturan Sistem.
 *
 * Aplikasi desktop: konfigurasi disimpan oleh main process Electron
 * (`mail:get/save/test`) lalu ditulis ke .env server lokal. Dulu settings ini
 * hanya bisa diisi lewat jendela wizard terpisah yang mudah terlewat, sekarang
 * satu tempat dengan konfigurasi lainnya.
 *
 * Website: mailer dibaca dari .env server, jadi hanya ditampilkan catatan.
 */

const EMPTY_FORM = {
  host: '',
  port: '587',
  username: '',
  password: '',
  fromAddress: '',
  fromName: '',
  testTo: '',
}

export default function EmailSettingsSection() {
  const isDesktop = Boolean(window.desktop?.isDesktop)
  const [form, setForm] = useState(EMPTY_FORM)
  const [hasPassword, setHasPassword] = useState(false)
  const [busy, setBusy] = useState(null) // 'save' | 'test' | null
  const [feedback, setFeedback] = useState(null) // { ok, message }

  useEffect(() => {
    if (!isDesktop || !window.desktop?.getMailSettings) return
    window.desktop
      .getMailSettings()
      .then((data) => {
        setHasPassword(Boolean(data.hasPassword))
        setForm((prev) => ({
          ...prev,
          host: data.host || '',
          port: data.port || '587',
          username: data.username || '',
          fromAddress: data.fromAddress || '',
          fromName: data.fromName || '',
        }))
      })
      .catch(() => setFeedback({ ok: false, message: 'Tak dapat membaca konfigurasi email.' }))
  }, [isDesktop])

  const setField = (key) => (event) => setForm((prev) => ({ ...prev, [key]: event.target.value }))

  const run = async (name, action) => {
    setBusy(name)
    setFeedback(null)
    try {
      const result = await action()
      setFeedback({ ok: Boolean(result?.ok), message: result?.message || 'Selesai.' })
      if (name === 'save' && result?.ok) setHasPassword(hasPassword || Boolean(form.password))
    } catch (err) {
      setFeedback({ ok: false, message: err?.message || 'Gagal menghubungi aplikasi desktop.' })
    } finally {
      setBusy(null)
    }
  }

  const handleSave = () => run('save', () => window.desktop.saveMailSettings({ ...form }))
  const handleTest = () => run('test', () => window.desktop.testMailSettings({ ...form }))

  if (!isDesktop) {
    return (
      <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
        <p className="flex items-center gap-1.5 text-sm font-semibold text-slate-700">
          <Mail className="h-4 w-4" />
          Email (SMTP)
        </p>
        <p className="mt-2 text-xs leading-relaxed text-slate-500">
          Pengaturan email pada versi website dibaca dari berkas <code className="text-slate-600">.env</code> di server
          (variabel <code className="text-slate-600">MAIL_*</code>). Untuk mengubahnya, masuk ke panel hosting atau
          edit <code className="text-slate-600">.env</code>, lalu jalankan{' '}
          <code className="text-slate-600">php artisan config:clear</code>. Formulir di bawah hanya berlaku untuk
          aplikasi desktop.
        </p>
      </div>
    )
  }

  return (
    <div className="space-y-3">
      <div className="rounded-xl border border-slate-200 p-4">
        <p className="flex items-center gap-1.5 text-sm font-semibold text-slate-700">
          <Mail className="h-4 w-4" />
          Server SMTP
        </p>
        <div className="mt-3 grid gap-3 sm:grid-cols-2">
          <label className="text-xs font-medium text-slate-600">
            Host SMTP
            <input
              value={form.host}
              onChange={setField('host')}
              placeholder="smtp-relay.brevo.com"
              className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-cyan-500 focus:outline-none"
            />
          </label>
          <label className="text-xs font-medium text-slate-600">
            Port
            <input
              value={form.port}
              onChange={setField('port')}
              placeholder="587"
              className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-cyan-500 focus:outline-none"
            />
          </label>
          <label className="text-xs font-medium text-slate-600">
            Username SMTP
            <input
              value={form.username}
              onChange={setField('username')}
              autoComplete="off"
              className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-cyan-500 focus:outline-none"
            />
          </label>
          <label className="text-xs font-medium text-slate-600">
            Password / SMTP Key
            <input
              type="password"
              value={form.password}
              onChange={setField('password')}
              placeholder={hasPassword ? 'â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢ (tersimpan)' : 'â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢'}
              autoComplete="new-password"
              className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-cyan-500 focus:outline-none"
            />
          </label>
          <label className="text-xs font-medium text-slate-600">
            Email Pengirim
            <input
              type="email"
              value={form.fromAddress}
              onChange={setField('fromAddress')}
              placeholder="notifikasi@example.com"
              className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-cyan-500 focus:outline-none"
            />
          </label>
          <label className="text-xs font-medium text-slate-600">
            Nama Pengirim
            <input
              value={form.fromName}
              onChange={setField('fromName')}
              placeholder="Peminjaman Barang PNP"
              className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-cyan-500 focus:outline-none"
            />
          </label>
          <label className="text-xs font-medium text-slate-600 sm:col-span-2">
            Email Tujuan untuk Pengujian
            <input
              type="email"
              value={form.testTo}
              onChange={setField('testTo')}
              placeholder="admin@example.com"
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
          {busy === 'save' ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
          Simpan Pengaturan
        </button>
        <button
          type="button"
          disabled={busy !== null || !form.testTo}
          onClick={handleTest}
          className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3.5 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50"
        >
          {busy === 'test' ? <Loader2 className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />}
          Kirim Email Tes
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
        Menyimpan atau menguji akan memuat ulang server lokal (backend) agar pengaturan langsung dipakai. Password yang
        tersimpan tidak pernah dikirim ke antarmuka; kolom kosong berarti password lama dipertahankan.
      </p>
    </div>
  )
}
