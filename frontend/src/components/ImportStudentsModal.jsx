import { useRef, useState } from 'react'
import { Download, FileSpreadsheet, FileUp, X } from 'lucide-react'
import api from '../api/axios'
import { downloadBlob } from '../utils/downloadBlob'

// Template berisi judul, petunjuk, header berformat, dan contoh baris.
// Diunduh dari backend lalu bisa dibuka di Excel / diunggah ke Google Sheets.

function ImportStudentsModal({ open, onClose, onImported }) {
  const fileInputRef = useRef(null)
  const [selectedFile, setSelectedFile] = useState(null)
  const [importing, setImporting] = useState(false)
  const [error, setError] = useState('')
  const [result, setResult] = useState(null)

  if (!open) return null

  const reset = () => {
    setSelectedFile(null)
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
    if (!selectedFile) {
      setError('Pilih file CSV terlebih dahulu.')
      return
    }

    setError('')
    setResult(null)
    setImporting(true)
    try {
      const formData = new FormData()
      formData.append('file', selectedFile)

      const response = await api.post('/students/import', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })

      setResult(response.data)
      setSelectedFile(null)
      if (fileInputRef.current) fileInputRef.current.value = ''
      onImported?.(response.data)
    } catch (requestError) {
      const data = requestError.response?.data
      setError(data?.message || 'Gagal mengimpor data mahasiswa.')
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
        <FileSection fileInputRef={fileInputRef} handleFileChange={handleFileChange} />
        {error && <div className="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>}
        {result && <ResultSection result={result} />}
        <Footer handleClose={handleClose} handleImport={handleImport} importing={importing} selectedFile={selectedFile} />
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
        <p className="mt-1 text-sm text-slate-500">Unggah file CSV hasil unduhan dari Google Sheets atau Excel.</p>
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

function Footer({ handleClose, handleImport, importing, selectedFile }) {
  return (
    <div className="flex justify-end gap-2">
      <button type="button" onClick={handleClose} className="rounded-lg border border-slate-300 px-4 py-2.5 font-medium text-slate-600 hover:bg-slate-50">
        Tutup
      </button>
      <button
        type="button"
        onClick={handleImport}
        disabled={importing || !selectedFile}
        className="inline-flex items-center gap-2 rounded-lg bg-cyan-600 px-4 py-2.5 font-medium text-white hover:bg-cyan-700 disabled:opacity-50"
      >
        <FileUp className="h-4 w-4" />
        {importing ? 'Mengimpor...' : 'Impor Sekarang'}
      </button>
    </div>
  )
}