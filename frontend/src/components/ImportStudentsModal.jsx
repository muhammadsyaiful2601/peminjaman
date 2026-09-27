import { useEffect, useRef, useState } from 'react'
import { Download, FileSpreadsheet, FileUp, RefreshCw, X } from 'lucide-react'
import api from '../api/axios'
import { downloadBlob } from '../utils/downloadBlob'

// Template berisi judul, petunjuk, header berformat, dan contoh baris.
// Diunduh dari backend lalu bisa dibuka di Excel / diunggah ke Google Sheets.

function ImportStudentsModal({ open, onClose, onImported }) {
  const fileInputRef = useRef(null)
  const [selectedFile, setSelectedFile] = useState(null)
  const [source, setSource] = useState('file')
  const [spreadsheet, setSpreadsheet] = useState('')
  // const [range, setRange] = useState('')
  const [importing, setImporting] = useState(false)
  const [error, setError] = useState('')
  const [result, setResult] = useState(null)

  useEffect(() => {
    if (!open) return

    const savedUrl = localStorage.getItem('student_sync_csv_url')
    if (savedUrl) {
      setSource('api')
      setSpreadsheet(savedUrl)
      return
    }

    api.get('/students/import/source')
      .then((response) => {
        const url = response.data.url || ''
        if (!url) return
        localStorage.setItem('student_sync_csv_url', url)
        setSource('api')
        setSpreadsheet(url)
      })
      .catch(() => {
        // URL belum tersimpan atau server belum tersedia.
      })
  }, [open])

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
        response = await api.post('/students/import', formData, {
          headers: { 'Content-Type': 'multipart/form-data' },
        })
      } else {
        let result
        if (window.desktop?.importStudentsFromCsv) {
          result = await window.desktop.importStudentsFromCsv(spreadsheet.trim())
        } else {
          const webResponse = await api.post('/students/import/csv-url', { url: spreadsheet.trim() })
          result = webResponse.data
        }
        if (!result.ok) throw new Error(result.message || 'Gagal mengimpor CSV Google Sheets.')
        response = { data: result }
      }

      if (source === 'api') localStorage.setItem('student_sync_csv_url', spreadsheet.trim())
      if (source === 'api') {
        await api.post('/students/import/source', { url: spreadsheet.trim() })
      }
      setResult(response.data)
      setSelectedFile(null)
      if (fileInputRef.current) fileInputRef.current.value = ''
      onImported?.(response.data)
    } catch (requestError) {
      const data = requestError.response?.data
      setError(data?.message || requestError.message || 'Gagal mengimpor data mahasiswa.')
      if (data?.errors) setResult({ errors: data.errors })
    } finally {
      setImporting(false)
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" onClick={handleClose}>
      <div className="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl" onClick={(event) => event.stopPropagation()}>
        <ModalHeader handleClose={handleClose} />
        <TemplateSection />
        <SourceTabs source={source} setSource={setSource} />
        {source === 'file'
          ? <FileSection fileInputRef={fileInputRef} handleFileChange={handleFileChange} />
          : <ApiSection spreadsheet={spreadsheet} setSpreadsheet={setSpreadsheet} />}
        {error && <div className="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>}
        {result && <ResultSection result={result} />}
        <Footer handleClose={handleClose} handleImport={handleImport} importing={importing} canImport={source === 'file' ? Boolean(selectedFile) : Boolean(spreadsheet.trim())} source={source} />
      </div>
    </div>
  )
}

export default ImportStudentsModal

function ModalHeader({ handleClose }) {
  return (
    <div className="mb-4 flex items-start justify-between">
      <div>
        <h2 className="text-lg font-bold text-slate-900">Impor Data Mahasiswa</h2>
        <p className="mt-1 text-sm text-slate-500">Impor dari file spreadsheet atau CSV Google Sheets yang sudah dipublikasikan.</p>
      </div>
      <button type="button" onClick={handleClose} className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup">
        <X className="h-5 w-5" />
      </button>
    </div>
  )
}

function TemplateSection() {
  return (
    <div className="mb-4 rounded-lg border border-cyan-200 bg-cyan-50 p-4">
      <div className="flex items-start gap-3">
        <FileSpreadsheet className="mt-0.5 h-5 w-5 shrink-0 text-cyan-600" />
        <div className="text-sm">
          <p className="font-medium text-cyan-900">Belum punya file?</p>
          <p className="mt-0.5 text-cyan-700">
            Unduh template Excel yang sudah berisi judul dan header kolom. Isi data mulai baris ke-4, lalu simpan sebagai CSV
            atau unggah langsung ke Google Sheets (<em>File → Import</em>) dan unduh kembali sebagai CSV.
          </p>
          <TemplateButton />
        </div>
      </div>
    </div>
  )
}

function TemplateButton() {
  const [downloading, setDownloading] = useState(false)

  const handleClick = async () => {
    setDownloading(true)
    try {
      const response = await api.get('/students/import/template', { responseType: 'blob' })
      await downloadBlob(response.data, 'template-impor-mahasiswa.xls')
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
        <code className="rounded bg-slate-100 px-1">NIM/NIP</code>, <code className="rounded bg-slate-100 px-1">Nama</code>,{' '}
        <code className="rounded bg-slate-100 px-1">Email</code>. NIM yang sudah ada akan diperbarui.
      </p>
    </div>
  )
}

function ApiSection({ spreadsheet, setSpreadsheet }) {
  return (
    <div className="mb-4 space-y-3">
      <label className="block text-sm font-medium text-slate-700">
        URL CSV Google Sheets
        <input value={spreadsheet} onChange={(event) => setSpreadsheet(event.target.value)} placeholder="https://docs.google.com/spreadsheets/d/.../pub?output=csv" className="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500" />
      </label>
      <p className="text-xs text-slate-500">Di Google Sheets pilih File → Bagikan → Publikasikan ke web, pilih format CSV, lalu tempel URL hasil publikasi. Tidak memerlukan API key.</p>
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