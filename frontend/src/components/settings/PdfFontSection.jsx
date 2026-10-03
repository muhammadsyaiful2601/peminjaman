import { useEffect, useState } from 'react'
import { Check, FileText, Loader2, Type } from 'lucide-react'
import api from '../../api/axios'

/**
 * Pemilihan font untuk seluruh dokumen PDF (surat resmi, laporan, bukti
 * peminjaman).
 *
 * Daftar font diambil dari backend karena hanya server yang tahu font mana
 * yang benar-benar bisa dirender Dompdf. Font sistem seperti Calibri atau
 * Segoe UI sengaja tidak ditawarkan: Dompdf akan menggantinya diam-diam.
 */
export default function PdfFontSection({ canEdit = true }) {
  const [fonts, setFonts] = useState([])
  const [current, setCurrent] = useState('')
  const [selected, setSelected] = useState('')
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [message, setMessage] = useState('')
  const [error, setError] = useState('')

  useEffect(() => {
    api.get('/pdf-font')
      .then((response) => {
        setFonts(response.data.fonts || [])
        setCurrent(response.data.current || '')
        setSelected(response.data.current || '')
      })
      .catch(() => setError('Daftar font PDF tidak dapat dimuat.'))
      .finally(() => setLoading(false))
  }, [])

  const dirty = selected !== current

  const save = async (event) => {
    event.preventDefault()
    if (!dirty) return

    setSaving(true)
    setMessage('')
    setError('')

    try {
      const response = await api.post('/pdf-font', { pdf_font: selected })
      setCurrent(response.data.current)
      setSelected(response.data.current)
      setMessage(response.data.message || 'Font PDF berhasil diperbarui.')
    } catch (err) {
      const data = err.response?.data
      setError(data?.message || 'Gagal menyimpan pilihan font PDF.')
      // Kembalikan ke nilai terakhir yang benar agar tampilan tidak
      // menyesatkan bila server menolak pilihan tadi.
      setSelected(current)
    } finally {
      setSaving(false)
    }
  }

  const activeFont = fonts.find((font) => font.key === current)

  return (
    <section className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
      <div className="mb-5 flex items-center gap-3">
        <Type className="h-5 w-5 text-cyan-600" />
        <div>
          <h2 className="font-semibold text-slate-900">Font dokumen PDF</h2>
          <p className="text-sm text-slate-500">
            Huruf yang dipakai pada surat resmi, laporan peminjaman, dan bukti peminjaman (QR).
          </p>
        </div>
      </div>

      {loading ? (
        <p className="flex items-center gap-2 text-sm text-slate-500">
          <Loader2 className="h-4 w-4 animate-spin" />
          Memuat daftar font...
        </p>
      ) : (
        <form onSubmit={save} className="space-y-4">
          <div className="grid gap-2 sm:grid-cols-2">
            {fonts.map((font) => {
              const active = font.key === current

              return (
                <label
                  key={font.key}
                  className={`flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition ${
                    active
                      ? 'border-cyan-500 bg-cyan-50 ring-2 ring-cyan-100'
                      : 'border-slate-200 hover:border-cyan-300 hover:bg-slate-50'
                  } ${canEdit ? '' : 'cursor-default'}`}
                >
                  <input
                    type="radio"
                    name="pdf_font"
                    value={font.key}
                    checked={selected === font.key}
                    disabled={!canEdit || saving}
                    onChange={() => setSelected(font.key)}
                    className="mt-1 h-4 w-4 accent-cyan-600"
                  />
                  <span className="min-w-0 flex-1">
                    <span className="flex items-center gap-2">
                      <span className="text-sm font-semibold text-slate-900">{font.label}</span>
                      {active && (
                        <span className="inline-flex items-center gap-1 rounded-full bg-cyan-600 px-2 py-0.5 text-[11px] font-semibold text-white">
                          <Check className="h-3 w-3" />
                          Dipakai
                        </span>
                      )}
                    </span>
                    <span className="mt-0.5 block text-xs text-slate-500">{font.note}</span>
                  </span>
                </label>
              )
            })}
          </div>

          {!canEdit && (
            <p className="text-xs text-slate-500">
              Hanya admin yang dapat mengganti font dokumen PDF. Font saat ini: {activeFont?.label || current}.
            </p>
          )}

          {canEdit && (
            <>
              {message && <p className="rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{message}</p>}
              {error && <p className="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{error}</p>}
              <button
                type="submit"
                disabled={!dirty || saving}
                className="inline-flex items-center gap-2 rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-700 disabled:opacity-50"
              >
                {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <FileText className="h-4 w-4" />}
                {saving ? 'Menyimpan...' : 'Simpan font PDF'}
              </button>
            </>
          )}
        </form>
      )}
    </section>
  )
}