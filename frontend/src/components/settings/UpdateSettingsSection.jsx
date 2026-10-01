import { useCallback, useEffect, useState } from 'react'
import { CloudDownload, Loader2, RefreshCw } from 'lucide-react'

/**
 * Seksi "Tentang & Pembaruan" di Pengaturan Sistem.
 * Menampilkan versi, kanal rilis, dan kendali pembaruan. Hanya tersedia di
 * aplikasi desktop karena pembaruan diambil dari GitHub Releases oleh
 * electron-updater di main process.
 */
export default function UpdateSettingsSection() {
  const [state, setState] = useState(null)
  const [busy, setBusy] = useState(null) // 'check' | 'download' | 'install'
  const [channelBusy, setChannelBusy] = useState(null) // 'stable' | 'beta'
  const [feedback, setFeedback] = useState(null) // { ok, message }

  useEffect(() => {
    const desktop = window.desktop
    if (!desktop?.getUpdateState) return undefined

    desktop
      .getUpdateState()
      .then(setState)
      .catch(() => setState({ state: 'error', message: 'Tak dapat membaca state pembaruan.' }))

    if (!desktop?.onUpdateState) return undefined
    // State digabung dengan yang sebelumnya agar kanal tidak hilang saat
    // push dari main process datang.
    return desktop.onUpdateState((next) => setState((prev) => ({ ...(prev || {}), ...next })))
  }, [])

  const checking = state?.state === 'checking' || busy === 'check'
  const downloading = state?.state === 'downloading' || busy === 'download'
  const downloaded = String(state?.state || '').startsWith('downloaded')
  const channel = state?.channel === 'beta' ? 'beta' : 'stable'

  const handleCheck = useCallback(async () => {
    setBusy('check')
    setFeedback(null)
    try {
      await window.desktop.checkForUpdates()
    } catch (err) {
      setFeedback({ ok: false, message: err?.message || 'Gagal memeriksa pembaruan.' })
      setBusy(null)
    }
  }, [])

  const handleDownload = async () => {
    setBusy('download')
    setFeedback(null)
    try {
      const result = await window.desktop.downloadUpdate()
      if (result && !result.ok) setFeedback({ ok: false, message: result.message || 'Gagal mengunduh pembaruan.' })
    } catch (err) {
      setFeedback({ ok: false, message: err?.message || 'Gagal mengunduh pembaruan.' })
    } finally {
      setBusy(null)
    }
  }

  const handleInstall = async () => {
    setBusy('install')
    try {
      await window.desktop.installUpdate()
    } catch (err) {
      setFeedback({ ok: false, message: err?.message || 'Gagal memasang pembaruan.' })
      setBusy(null)
    }
  }

  const handleChannel = async (value) => {
    setChannelBusy(value)
    setFeedback(null)
    try {
      await window.desktop.setUpdateChannel(value)
      const fresh = await window.desktop.getUpdateState()
      setState((prev) => ({ ...(prev || {}), ...fresh }))
      setFeedback({
        ok: true,
        message: value === 'beta' ? 'Kanal Beta aktif.' : 'Kanal Stabil aktif.',
      })
    } catch (err) {
      setFeedback({ ok: false, message: err?.message || 'Gagal mengganti kanal.' })
    } finally {
      setChannelBusy(null)
    }
  }

  return (
    <div className="space-y-3">
      <div className="rounded-xl border border-slate-200 p-4 text-sm text-slate-600">
        <div className="flex items-center justify-between">
          <span className="flex items-center gap-1.5 font-semibold text-slate-700">
            <CloudDownload className="h-4 w-4" />
            Pembaruan Aplikasi
          </span>
          {checking ? (
            <span className="flex items-center gap-1 text-cyan-600">
              <Loader2 className="h-3.5 w-3.5 animate-spin" /> Memeriksa...
            </span>
          ) : (
            <button type="button" onClick={handleCheck} className="flex items-center gap-1 text-cyan-600 hover:text-cyan-700">
              <RefreshCw className="h-3.5 w-3.5" /> Periksa
            </button>
          )}
        </div>
        <p className="mt-2 text-xs text-slate-500">
          {state?.state === 'downloading' && state?.percent != null
            ? `Mengunduh versi ${state.version}... ${Math.round(state.percent)}%`
            : state?.state === 'available'
              ? `Pembaruan tersedia: ${state.version}`
              : `Versi sekarang ${state?.version || '-'} - aplikasi sudah versi terbaru.`}
        </p>
        {state?.state === 'available' && state.releaseNotes && (
          <div className="mt-3 rounded-lg border border-cyan-100 bg-cyan-50 p-3">
            <p className="text-xs font-semibold text-slate-700">Perbaikan versi {state.version}</p>
            <p className="mt-1 whitespace-pre-line text-xs text-slate-600">{state.releaseNotes}</p>
          </div>
        )}
        <div className="mt-3 flex flex-wrap gap-2">
          <button
            type="button"
            disabled={busy !== null || !downloaded}
            onClick={handleInstall}
            className="inline-flex items-center gap-1.5 rounded-lg bg-cyan-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-cyan-700 disabled:opacity-50"
          >
            {busy === 'install' && <Loader2 className="h-3.5 w-3.5 animate-spin" />}
            Pasang & Mulai Ulang
          </button>
          <button
            type="button"
            disabled={busy !== null || downloading}
            onClick={handleDownload}
            className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50"
          >
            {downloading ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <CloudDownload className="h-3.5 w-3.5" />}
            {downloaded ? 'Sudah diunduh' : 'Unduh Pembaruan'}
          </button>
        </div>
      </div>

      <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
        <div className="flex items-center justify-between gap-4">
          <div>
            <p className="text-sm font-semibold text-slate-700">Kanal Pembaruan</p>
            <p className="mt-1 text-xs text-slate-500">
              Stabil memakai rilis final. Beta mengikuti rilis uji coba lebih awal agar fitur baru bisa dicoba sebelum rilis resmi.
            </p>
          </div>
          <div className="flex shrink-0 overflow-hidden rounded-lg border border-slate-200 bg-white text-xs font-semibold">
            {[
              ['stable', 'Stabil'],
              ['beta', 'Beta'],
            ].map(([value, label]) => (
              <button
                key={value}
                type="button"
                disabled={channelBusy !== null}
                onClick={() => handleChannel(value)}
                className={`px-3 py-1.5 ${
                  channel === value ? 'bg-cyan-600 text-white' : 'text-slate-500 hover:bg-slate-50'
                }`}
              >
                {channelBusy === value ? '...' : label}
              </button>
            ))}
          </div>
        </div>
        <p className="mt-3 text-xs text-slate-500">
          {channel === 'beta' ? 'Kanal Beta: menerima rilis prarilis.' : 'Kanal Stabil: hanya menerima rilis final.'}
        </p>
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
    </div>
  )
}