import { useCallback, useEffect, useRef, useState } from 'react'
import { Download, FileSpreadsheet, FileUp, RefreshCw, X } from 'lucide-react'
import api from '../api/axios'
import { downloadBlob } from '../utils/downloadBlob'
import SheetsWebhookSettings from './borrowers/SheetsWebhookSettings'
import {
  DEFAULT_BORROWER_TYPE,
  SPREADSHEET_BORROWER_TYPES,
  borrowerTypeLabel,
  hasSpreadsheetSupport,
  normalizeBorrowerType,
  syncStorageKey,
} from '../utils/borrowerTypes'

// Template berisi judul, petunjuk, header berformat, dan contoh baris.
// Diunduh dari backend lalu bisa dibuka di Excel / diunggah ke Google Sheets.

/**
 * Modal impor data peminjam.
 *
 * Mahasiswa, tendik, dan dosen punya spreadsheet Google Sheets masing-masing
 * dengan alur yang sama persis: pilih jenis -> tempel URL CSV terpublikasi ->
 * impor (dan seterusnya ikut tersinkron otomatis setiap 5 menit).
 *
 * @param {object}   props
 * @param {boolean}  props.open            Modal terbuka atau tidak.
 * @param {Function} props.onClose          Dipanggil saat modal ditutup.
 * @param {Function} [props.onImported]    Dipanggil dengan hasil impor.
 * @param {string}   [props.initialType]   Jenis yang dipakai saat modal dibuka
 *                                         (default: tab yang sedang aktif).
 * @param {string[]} [props.allowedTypes]  Kelompok yang boleh dipilih. Bila
 *                                         diisi satu jenis, pemilih disembunyikan.
 * @param {object}   [props.sources]       Sumber spreadsheet per jenis dari halaman induk.
 * @param {Function} [props.onSourcesChange] Dipanggil saat URL jenis terpilih berubah.
 */
function ImportStudentsModal({ open, onClose, onImported, initialType, allowedTypes, sources, onSourcesChange }) {
  const fileInputRef = useRef(null)
  const [selectedFile, setSelectedFile] = useState(null)
  const [source, setSource] = useState('file')
  const [spreadsheet, setSpreadsheet] = useState('')
  // const [range, setRange] = useState('')
  const [importing, setImporting] = useState(false)
  const [error, setError] = useState('')
  const [result, setResult] = useState(null)
  const [type, setType] = useState(DEFAULT_BORROWER_TYPE)
  // URL webhook tulis-balik per jenis, dibaca dari halaman induk yang sudah
  // memuatnya dari backend. Nilai di sini hanya dipakai selama modal terbuka.
  const [webhookUrls, setWebhookUrls] = useState({})

  // URL milik sebuah jenis, dibaca dari cache browser lalu halaman induk.
  // Dibuat stabil dengan useCallback supaya aman dipakai di dalam useEffect.
  const urlFor = useCallback(
    (value) => localStorage.getItem(syncStorageKey(value)) || sources?.[value]?.url || '',
    [sources],
  )

  useEffect(() => {
    if (!open) return

    // Modal dibuka pada tab yang sedang aktif bila tab itu punya spreadsheet.
    // `allowedTypes` membatasi pilihan agar tidak mungkin mengimpor ke kelompok
    // yang tidak ditangani halaman pemanggil (mis. mahasiswa dari halaman
    // pegawai).
    const pool = allowedTypes?.length ? allowedTypes : SPREADSHEET_BORROWER_TYPES.map((t) => t.value)
    const wanted = pool.includes(normalizeBorrowerType(initialType))
      ? normalizeBorrowerType(initialType)
      : (pool.includes(DEFAULT_BORROWER_TYPE) ? DEFAULT_BORROWER_TYPE : pool[0])
    const savedUrl = urlFor(wanted)

    setType(wanted)
    setSpreadsheet(savedUrl)
    setSource(savedUrl ? 'api' : 'file')
  }, [open, initialType, allowedTypes, urlFor])

  // Ganti jenis -> muat URL milik jenis itu (kalau ada).
  const handleTypeChange = (next) => {
    const value = normalizeBorrowerType(next)
    const savedUrl = urlFor(value)

    setType(value)
    setError('')
    setResult(null)
    setSpreadsheet(savedUrl)
    setSource(savedUrl ? 'api' : 'file')
  }

  if (!open) return null

  const reset = () => {
    setSelectedFile(null)
    setSource('file')
    setSpreadsheet('')
    // setRange('')
    setError('')
    setResult(null)
    setImporting(false)
    if (fileInputRef.current) fileInputRef.current.value = ''
  }

  const handleClose = () => {
    reset()
    onClose()
  }

  const handleFileChange = (event) => {
    const file = event.target.files?.[0] || null
    setSelectedFile(file)
    setError('')
    setResult(null)
  }

  const handleImport = async () => {
    if (source === 'file' && !selectedFile) {
      setError('Pilih file CSV terlebih dahulu.')
      return
    }
    if (source === 'api' && !spreadsheet.trim()) {
      setError('Masukkan URL CSV Google Sheets terlebih dahulu.')
      return
    }

    setError('')
    setResult(null)
    setImporting(true)
    try {
      let response
      if (source === 'file') {
        const formData = new FormData()
        formData.append('file', selectedFile)
        // Seluruh baris file dipaksa menjadi jenis yang dipilih, jadi file
        // tendik/dosen memakai Role untuk menentukan kategori setiap baris.
        formData.append('type', type)
        response = await api.post('/students/import', formData, {
          headers: { 'Content-Type': 'multipart/form-data' },
        })
      } else {
        let result
        if (window.desktop?.importStudentsFromCsv) {
          result = await window.desktop.importStudentsFromCsv(spreadsheet.trim(), type)
        } else {
          const webResponse = await api.post('/students/import/csv-url', { url: spreadsheet.trim(), type })
          result = webResponse.data
        }
        if (!result.ok) throw new Error(result.message || 'Gagal mengimpor CSV Google Sheets.')
        response = { data: result }
      }

      if (source === 'api') {
        // URL disimpan pada kunci milik jenis ini supaya sinkronisasi
        // otomatis berikutnya memakai spreadsheet yang sama.
        localStorage.setItem(syncStorageKey(type), spreadsheet.trim())
        await api.post('/students/import/source', { url: spreadsheet.trim(), type })
        onSourcesChange?.((current) => ({
          ...current,
          [type]: {
            ...(current?.[type] || {}),
            url: spreadsheet.trim(),
            lastSyncedAt: result?.synced_at || current?.[type]?.lastSyncedAt || '',
          },
        }))
      }
      setResult(response.data)
      setSelectedFile(null)
      if (fileInputRef.current) fileInputRef.current.value = ''
      onImported?.({ ...response.data, type })
    } catch (requestError) {
      const data = requestError.response?.data
      setError(data?.message || requestError.message || 'Gagal mengimpor data peminjam.')
      if (data?.errors) setResult({ errors: data.errors })
    } finally {
      setImporting(false)
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" onClick={handleClose}>
      {/*
        Batas tinggi + area isi yang bisa digulir. Tanpa ini modal memanjang
        mengikuti isinya (termasuk kode Apps Script yang panjang) sehingga
        bagian bawah terpotong di layar kecil dan tombol Tutup tidak terjangkau.
        Header & footer sengaja `shrink-0` agar tetap terlihat.
      */}
      <div className="flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-xl bg-white shadow-xl" onClick={(event) => event.stopPropagation()}>
        <div className="shrink-0 px-6 pt-6">
          <ModalHeader handleClose={handleClose} />
        </div>

        <div className="flex-1 overflow-y-auto px-6 pb-4">
          <TypeSection type={type} onChange={handleTypeChange} allowedTypes={allowedTypes} />
          <TemplateSection type={type} />
          <SourceTabs source={source} setSource={setSource} />
          {source === 'file'
            ? <FileSection fileInputRef={fileInputRef} handleFileChange={handleFileChange} />
            : <ApiSection
                type={type}
                spreadsheet={spreadsheet}
                setSpreadsheet={setSpreadsheet}
                webhookUrl={webhookUrls[type] || sources?.[type]?.webhookUrl || ''}
                onWebhookSaved={(saved) => setWebhookUrls((current) => ({ ...current, [type]: saved }))}
              />}
          {error && <div className="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>}
          {result && <ResultSection result={result} />}
        </div>

        <div className="shrink-0 border-t border-slate-200 px-6 py-4">
          <Footer handleClose={handleClose} handleImport={handleImport} importing={importing} canImport={source === 'file' ? Boolean(selectedFile) : Boolean(spreadsheet.trim())} source={source} />
        </div>
      </div>
    </div>
  )
}

export default ImportStudentsModal

/**
 * Pemilih kelompok peminjam. Tiap kelompok punya spreadsheet Google Sheets
 * sendiri dengan alur identik, jadi petugas cukup menautkan URL per kelompok.
 *
 * Saat hanya ada satu kelompok yang boleh dipakai (halaman mahasiswa), pemilih
 * disembunyikan karena sudah tidak ada pilihan lain.
 */
function TypeSection({ type, onChange, allowedTypes }) {
  const pool = allowedTypes?.length ? allowedTypes : SPREADSHEET_BORROWER_TYPES.map((t) => t.value)
  const options = SPREADSHEET_BORROWER_TYPES.filter((item) => pool.includes(item.value))

  if (options.length <= 1) {
    return (
      <div className="mb-4 rounded-lg border border-cyan-200 bg-cyan-50 px-4 py-3 text-sm text-cyan-900">
        Data akan diimpor sebagai <strong>{borrowerTypeLabel(type)}</strong>. Kolom
        &ldquo;Jenis&rdquo; pada spreadsheet tidak perlu diisi.
      </div>
    )
  }

  return (
    <div className="mb-4">
      <p className="mb-2 text-sm font-medium text-slate-700">Data yang akan diimpor</p>
      <div className="grid gap-2" style={{ gridTemplateColumns: `repeat(${Math.min(options.length, 3)}, minmax(0, 1fr))` }}>
        {options.map((item) => {
          const active = type === item.value

          return (
            <button
              key={item.value}
              type="button"
              onClick={() => onChange(item.value)}
              className={`rounded-lg border px-3 py-2 text-sm font-medium transition ${
                active
                  ? 'border-cyan-600 bg-cyan-600 text-white'
                  : 'border-slate-300 bg-white text-slate-600 hover:border-cyan-300 hover:bg-cyan-50'
              }`}
            >
              {item.label}
            </button>
          )
        })}
      </div>
      <p className="mt-2 text-xs text-slate-500">
        Seluruh baris dari file atau URL ini akan disimpan sebagai
        {' '}<strong>{borrowerTypeLabel(type)}</strong>, jadi kolom &ldquo;Jenis&rdquo; pada spreadsheet tidak wajib diisi.
      </p>
    </div>
  )
}

function ModalHeader({ handleClose }) {
  return (
    <div className="mb-4 flex items-start justify-between">
      <div>
        <h2 className="text-lg font-bold text-slate-900">Impor Data Peminjam</h2>
        <p className="mt-1 text-sm text-slate-500">Impor dari file spreadsheet atau CSV Google Sheets yang sudah dipublikasikan. Bisa berisi mahasiswa, tendik, dosen, dan peminjam umum.</p>
      </div>
      <button type="button" onClick={handleClose} className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup">
        <X className="h-5 w-5" />
      </button>
    </div>
  )
}

function TemplateSection({ type }) {
  return (
    <div className="mb-4 rounded-lg border border-cyan-200 bg-cyan-50 p-4">
      <div className="flex items-start gap-3">
        <FileSpreadsheet className="mt-0.5 h-5 w-5 shrink-0 text-cyan-600" />
        <div className="text-sm">
          <p className="font-medium text-cyan-900">Belum punya file?</p>
          <p className="mt-0.5 text-cyan-700">
            Unduh template Excel untuk kelompok{' '}
            <strong>{borrowerTypeLabel(type)}</strong> — sudah berisi judul, petunjuk, dan header kolom. Isi data mulai
            dari baris yang ditunjukkan pada panduan, lalu simpan sebagai CSV atau unggah langsung ke Google Sheets
            (<em>File → Import</em>) dan unduh kembali sebagai CSV. Kolom
            {' '}<strong>Role</strong> dan <strong>Jabatan / Unit Kerja</strong> mengikuti panduan pada template.
            NIP pegawai boleh dikosongkan; email wajib diisi.
          </p>
          <TemplateButton type={type} />
        </div>
      </div>
    </div>
  )
}

function TemplateButton({ type }) {
  const [downloading, setDownloading] = useState(false)

  const handleClick = async () => {
    setDownloading(true)
    try {
      const response = await api.get('/students/import/template', {
        params: { type },
        responseType: 'blob',
      })
      await downloadBlob(response.data, `template-impor-${type}.xls`)
    } catch {
      // Gagal unduh: biarkan pengguna mencoba lagi.
    } finally {
      setDownloading(false)
    }
  }

  return (
    <button
      type="button"
      onClick={handleClick}
      disabled={downloading}
      className="mt-2 inline-flex items-center gap-2 rounded-lg bg-cyan-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-cyan-700 disabled:opacity-50"
    >
      <Download className="h-3.5 w-3.5" />
      {downloading ? 'Mengunduh...' : 'Unduh Template (Excel)'}
    </button>
  )
}

function SourceTabs({ source, setSource }) {
  return (
    <div className="mb-4 grid grid-cols-2 gap-2 rounded-lg bg-slate-100 p-1">
      <button type="button" onClick={() => setSource('file')} className={`rounded-md px-3 py-2 text-sm font-medium ${source === 'file' ? 'bg-white text-cyan-700 shadow-sm' : 'text-slate-500'}`}>
        File Spreadsheet
      </button>
      <button type="button" onClick={() => setSource('api')} className={`inline-flex items-center justify-center gap-2 rounded-md px-3 py-2 text-sm font-medium ${source === 'api' ? 'bg-white text-cyan-700 shadow-sm' : 'text-slate-500'}`}>
        <RefreshCw className="h-3.5 w-3.5" /> Google Sheets CSV
      </button>
    </div>
  )
}

function FileSection({ fileInputRef, handleFileChange }) {
  return (
    <div className="mb-4">
      <label className="mb-1.5 block text-sm font-medium text-slate-700">File CSV / Excel</label>
      <input
        ref={fileInputRef}
        type="file"
        accept=".csv,.xlsx,.xls,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel"
        onChange={handleFileChange}
        className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200"
      />
      <p className="mt-1.5 text-xs text-slate-500">
        Format yang didukung: <code className="rounded bg-slate-100 px-1">.xlsx</code>,{' '}
        <code className="rounded bg-slate-100 px-1">.xls</code>, <code className="rounded bg-slate-100 px-1">.csv</code>. Kolom wajib:{' '}
        <code className="rounded bg-slate-100 px-1">Nama</code> dan <code className="rounded bg-slate-100 px-1">Email</code>.
        NIM wajib untuk mahasiswa; NIP opsional untuk Tendik dan Dosen. Email dipakai untuk mencocokkan pembaruan.
      </p>
    </div>
  )
}

function ApiSection({ type, spreadsheet, setSpreadsheet, webhookUrl, onWebhookSaved }) {
  const label = borrowerTypeLabel(type)
  const shared = type === 'tendik' || type === 'dosen'

  return (
    <div className="mb-4 space-y-3">
      <label className="block text-sm font-medium text-slate-700">
        URL CSV Google Sheets — {label}
        <input value={spreadsheet} onChange={(event) => setSpreadsheet(event.target.value)} placeholder="https://docs.google.com/spreadsheets/d/.../pub?output=csv" className="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500" />
      </label>
      <p className="text-xs text-slate-500">
        Di Google Sheets pilih <em>File → Bagikan → Publikasikan ke web</em>, pilih format CSV, lalu tempel URL hasil
        publikasi. Tidak memerlukan API key. URL ini tersimpan terpisah untuk kelompok{' '}
        <strong>{label}</strong> dan akan ditarik otomatis setiap 5 menit.
      </p>

      <p className="rounded-lg border border-cyan-200 bg-cyan-50 px-3 py-2 text-xs text-cyan-900">
        {shared
          ? 'Tendik & Dosen memakai satu spreadsheet bersama. Kategori dilihat dari Jabatan / Unit Kerja: jika memuat kata Dosen, data masuk kategori Dosen; selain itu masuk kategori Tendik. Role hanya menjelaskan peran kerja dan boleh dikosongkan. NIP boleh dikosongkan; email wajib diisi.'
          : 'Spreadsheet mahasiswa berdiri sendiri dan tidak ikut berubah karena perubahan data pegawai.'}
      </p>

      <SheetsWebhookSettings type={type} webhookUrl={webhookUrl} onSaved={onWebhookSaved} />
    </div>
  )
}

function ResultSection({ result }) {
  return (
    <div className="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
      <p>{result.message || 'Impor selesai.'}</p>
      {result.errors?.length > 0 && (
        <details className="mt-2">
          <summary className="cursor-pointer font-medium">Lihat baris yang dilewati ({result.errors.length})</summary>
          <ul className="mt-1 list-inside list-disc text-xs">
            {result.errors.map((errorMessage, index) => (
              <li key={index}>{errorMessage}</li>
            ))}
          </ul>
        </details>
      )}
    </div>
  )
}

function Footer({ handleClose, handleImport, importing, canImport, source }) {
  return (
    <div className="flex justify-end gap-2">
      <button type="button" onClick={handleClose} className="rounded-lg border border-slate-300 px-4 py-2.5 font-medium text-slate-600 hover:bg-slate-50">
        Tutup
      </button>
      <button
        type="button"
        onClick={handleImport}
        disabled={importing || !canImport}
        className="inline-flex items-center gap-2 rounded-lg bg-cyan-600 px-4 py-2.5 font-medium text-white hover:bg-cyan-700 disabled:opacity-50"
      >
        <FileUp className="h-4 w-4" />
        {importing ? 'Mengimpor...' : source === 'api' ? 'Ambil & Impor' : 'Impor Sekarang'}
      </button>
    </div>
  )
}