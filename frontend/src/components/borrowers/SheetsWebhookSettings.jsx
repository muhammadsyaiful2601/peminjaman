import { useState } from 'react'
import { BookOpen, CheckCircle2, Link2, Loader2, Save, Unlink } from 'lucide-react'
import api from '../../api/axios'
import { borrowerTypeLabel, normalizeBorrowerType } from '../../utils/borrowerTypes'
import { APPS_SCRIPT_CODE } from './appsScript'
import WebhookTutorial from './WebhookTutorial'

/**
 * Pengaturan tulis-balik (write-back) Jabatan ke Google Sheets.
 *
 * CSV terpublikasinya hanya bisa dibaca, jadi jabatan yang diubah di aplikasi
 * tidak bisa dikirim balik ke sana. Bagian ini menyimpan URL Web App Google
 * Apps Script; setiap kali petugas menyimpan jabatan, aplikasi mengirim POST
 * ke URL tersebut.
 *
 * @param {object}   props
 * @param {string}   props.type         Jenis peminjam (mahasiswa/tendik/dosen).
 * @param {string}   [props.webhookUrl]  URL webhook yang tersimpan.
 * @param {Function} [props.onSaved]     Dipanggil dengan URL tersimpan baru.
 */
export default function SheetsWebhookSettings({ type, webhookUrl = '', onSaved }) {
  const value = normalizeBorrowerType(type)
  const label = borrowerTypeLabel(value)
  const [url, setUrl] = useState(webhookUrl)
  const [saving, setSaving] = useState(false)
  const [testing, setTesting] = useState(false)
  const [message, setMessage] = useState('')
  const [error, setError] = useState('')
  const [showTutorial, setShowTutorial] = useState(false)
  const [copied, setCopied] = useState(false)

  const active = Boolean(url.trim())

  const save = async (event) => {
    event.preventDefault()
    setSaving(true)
    setMessage('')
    setError('')

    try {
      const response = await api.post('/students/webhook', { url: url.trim(), type: value })
      setMessage(response.data.message)
      onSaved?.(response.data.webhook_url || '')
    } catch (requestError) {
      setError(requestError.response?.data?.message || 'Gagal menyimpan URL webhook.')
    } finally {
      setSaving(false)
    }
  }

  const test = async () => {
    setTesting(true)
    setMessage('')
    setError('')

    try {
      const response = await api.post('/students/webhook/test', { type: value })
      setMessage(response.data.message)
    } catch (requestError) {
      setError(requestError.response?.data?.message || 'Webhook tidak dapat dihubungi.')
    } finally {
      setTesting(false)
    }
  }

  const copyScript = async () => {
    try {
      await navigator.clipboard.writeText(APPS_SCRIPT_CODE)
      setCopied(true)
      setTimeout(() => setCopied(false), 2000)
    } catch {
      setError('Tidak dapat menyalin otomatis. Blok kode di bawah lalu salin manual.')
    }
  }

  return (
    <div className="rounded-lg border border-slate-200 bg-slate-50 p-4">
      <div className="mb-3 flex items-start gap-2">
        <Link2 className="mt-0.5 h-4 w-4 shrink-0 text-cyan-600" />
        <div>
          <p className="text-sm font-semibold text-slate-800">Tulis balik Jabatan ke spreadsheet — {label}</p>
          <p className="text-xs text-slate-500">
            Tanpa pengaturan ini spreadsheet hanya dibaca (Sheet → Aplikasi). Isi URL Web App Google Apps Script
            agar jabatan yang diubah di aplikasi ikut dikirim ke spreadsheet.
          </p>
        </div>
      </div>

      <form onSubmit={save} className="space-y-3">
        <input
          value={url}
          onChange={(event) => setUrl(event.target.value)}
          placeholder="https://script.google.com/macros/s/AKfycb.../exec"
          className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500"
        />

        <div className="flex flex-wrap gap-2">
          <button
            type="submit"
            disabled={saving}
            className="inline-flex items-center gap-2 rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700 disabled:opacity-50"
          >
            {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
            Simpan Webhook
          </button>

          {active && (
            <>
              <button
                type="button"
                onClick={test}
                disabled={testing || saving}
                className="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50"
              >
                {testing ? <Loader2 className="h-4 w-4 animate-spin" /> : <CheckCircle2 className="h-4 w-4" />}
                {testing ? 'Menguji...' : 'Uji Kirim'}
              </button>
              <button
                type="button"
                onClick={() => setUrl('')}
                className="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
              >
                <Unlink className="h-4 w-4" />
                Matikan
              </button>
            </>
          )}

          <button
            type="button"
            onClick={() => setShowTutorial(true)}
            className="inline-flex items-center gap-2 rounded-lg border border-cyan-200 bg-white px-4 py-2 text-sm font-medium text-cyan-700 hover:bg-cyan-50"
          >
            <BookOpen className="h-4 w-4" />
            Tutorial Lengkap
          </button>
        </div>
      </form>

      {message && <p className="mt-3 rounded-lg bg-emerald-50 px-3 py-2 text-xs text-emerald-700">{message}</p>}
      {error && <p className="mt-3 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-700">{error}</p>}

      {showTutorial && (
        <WebhookTutorial
          label={label}
          copied={copied}
          copyScript={copyScript}
          onClose={() => setShowTutorial(false)}
        />
      )}
    </div>
  )
}

