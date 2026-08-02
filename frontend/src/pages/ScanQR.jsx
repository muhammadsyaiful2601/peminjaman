import { useEffect, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Html5Qrcode } from 'html5-qrcode'
import api from '../api/axios'
import {
  ScanLine,
  CameraOff,
  CheckCircle,
  XCircle,
  Package,
  User,
  Upload,
  KeyRound,
  Undo2,
} from 'lucide-react'

function ScanQR() {
  const navigate = useNavigate()
  const scannerRef = useRef(null)
  const fileInputRef = useRef(null)

  const [activeTab, setActiveTab] = useState('scan') // scan | upload | code
  const [scanning, setScanning] = useState(false)
  const [error, setError] = useState('')
  const [result, setResult] = useState(null)
  const [processing, setProcessing] = useState(false)
  const [loanCode, setLoanCode] = useState('')
  const [returnForm, setReturnForm] = useState({ condition: 'bagus', note: '' })
  const [actionLoading, setActionLoading] = useState(false)

  // Start scanner when scanning becomes true
  useEffect(() => {
    if (!scanning || activeTab !== 'scan') return

    let cancelled = false
    let scanner = null

    const startScanner = async () => {
      try {
        scanner = new Html5Qrcode('qr-reader')
        scannerRef.current = scanner

        await scanner.start(
          { facingMode: 'environment' },
          { fps: 10, qrbox: { width: 250, height: 250 } },
          async (decodedText) => {
            if (cancelled) return
            cancelled = true

            try {
              await scanner.stop()
            } catch (e) {}

            scanner.clear()
            scannerRef.current = null
            setScanning(false)
            setProcessing(true)

            const uuidPattern = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i
            if (!uuidPattern.test(decodedText.trim())) {
              setError('QR Code tidak valid. Pastikan ini adalah QR Code transaksi peminjaman.')
              setProcessing(false)
              return
            }

            try {
              const response = await api.get(`/loans/qr/${decodedText.trim()}`)
              setResult(response.data.loan)
              setProcessing(false)
            } catch (err) {
              setError(err.response?.data?.message || 'Transaksi tidak ditemukan')
              setProcessing(false)
            }
          },
          () => {}
        )
      } catch (err) {
        if (!cancelled) {
          setError('Tidak dapat mengakses kamera. Pastikan izin kamera diberikan dan gunakan HTTPS atau localhost.')
          setScanning(false)
        }
      }
    }

    const timer = setTimeout(startScanner, 100)

    return () => {
      cancelled = true
      clearTimeout(timer)
      if (scannerRef.current) {
        try {
          scannerRef.current.stop().then(() => {
            scannerRef.current?.clear()
          }).catch(() => {})
        } catch (e) {}
        scannerRef.current = null
      }
    }
  }, [scanning, activeTab])

  // Cleanup on unmount
  useEffect(() => {
    return () => {
      if (scannerRef.current) {
        scannerRef.current.stop().catch(() => {})
      }
    }
  }, [])

  const stopScanner = async () => {
    if (scannerRef.current) {
      try {
        await scannerRef.current.stop()
        scannerRef.current.clear()
      } catch (e) {}
      scannerRef.current = null
    }
    setScanning(false)
  }

  // Handle PDF upload
  const handlePdfUpload = async (e) => {
    e.preventDefault()
    setError('')
    setProcessing(true)

    const file = fileInputRef.current?.files[0]
    if (!file) {
      setError('Pilih file PDF terlebih dahulu.')
      setProcessing(false)
      return
    }

    const formData = new FormData()
    formData.append('pdf', file)

    try {
      const response = await api.post('/loans/upload-pdf', formData)
      setResult(response.data.loan)
    } catch (err) {
      setError(err.response?.data?.message || 'Gagal membaca PDF.')
    } finally {
      setProcessing(false)
    }
  }

  // Handle code lookup
  const handleCodeLookup = async (e) => {
    e.preventDefault()
    setError('')
    setProcessing(true)

    if (!loanCode.trim()) {
      setError('Masukkan kode peminjaman terlebih dahulu.')
      setProcessing(false)
      return
    }

    try {
      const response = await api.get(`/loans/code/${loanCode.trim()}`)
      setResult(response.data.loan)
    } catch (err) {
      setError(err.response?.data?.message || 'Kode peminjaman tidak ditemukan.')
    } finally {
      setProcessing(false)
    }
  }

  // Handle return (auto-return when verified)
  const handleReturn = async (e) => {
    e.preventDefault()
    if (!window.confirm('Konfirmasi pengembalian barang ini?')) return
    setActionLoading(true)
    try {
      await api.post(`/loans/${result.id}/return`, {
        condition_on_return: returnForm.condition,
        condition_note: returnForm.note || undefined,
      })
      // Refresh loan data
      const response = await api.get(`/loans/${result.id}`)
      setResult(response.data.loan)
    } catch (err) {
      alert(err.response?.data?.message || 'Gagal memproses pengembalian')
    } finally {
      setActionLoading(false)
    }
  }

  const handleApprove = async () => {
    if (!window.confirm('Setujui peminjaman ini? Barang akan diserahkan.')) return
    setActionLoading(true)
    try {
      await api.post(`/loans/${result.id}/approve`)
      const response = await api.get(`/loans/${result.id}`)
      setResult(response.data.loan)
    } catch (err) {
      alert(err.response?.data?.message || 'Gagal menyetujui')
    } finally {
      setActionLoading(false)
    }
  }

  const resetResult = () => {
    setResult(null)
    setError('')
    setLoanCode('')
    setReturnForm({ condition: 'bagus', note: '' })
    if (fileInputRef.current) fileInputRef.current.value = ''
  }

  const statusLabels = {
    pending: { text: 'Menunggu', className: 'bg-amber-100 text-amber-700' },
    borrowed: { text: 'Dipinjam', className: 'bg-emerald-100 text-emerald-700' },
    returned: { text: 'Dikembalikan', className: 'bg-green-100 text-green-700' },
    rejected: { text: 'Ditolak', className: 'bg-red-100 text-red-700' },
  }

  const tabs = [
    { id: 'scan', label: 'Scan QR', icon: ScanLine },
    { id: 'upload', label: 'Upload PDF', icon: Upload },
    { id: 'code', label: 'Masukkan Kode', icon: KeyRound },
  ]

  return (
    <div className="max-w-3xl mx-auto">
      <div className="mb-8">
        <h1 className="text-2xl font-bold text-slate-900">Verifikasi Peminjaman</h1>
        <p className="text-slate-500 mt-1">
          Pilih metode verifikasi: scan QR, upload PDF, atau masukkan kode peminjaman
        </p>
      </div>

      {error && (
        <div className="mb-6 bg-red-50 border border-red-200 text-red-600 text-sm rounded-lg px-4 py-3 flex items-start gap-2">
          <XCircle className="w-5 h-5 shrink-0 mt-0.5" />
          <div className="flex-1">
            {error}
            <button onClick={() => setError('')} className="block mt-2 text-red-700 font-medium hover:underline">
              Tutup
            </button>
          </div>
        </div>
      )}

      {/* Result */}
      {result ? (
        <div className="bg-white rounded-xl border border-slate-200 overflow-hidden">
          <div className="px-6 py-4 bg-emerald-50 border-b border-emerald-200 flex items-center gap-2">
            <CheckCircle className="w-5 h-5 text-emerald-600" />
            <h2 className="font-semibold text-emerald-700">Transaksi Ditemukan</h2>
          </div>

          <div className="p-6 space-y-6">
            {/* Loan Code & Status */}
            <div className="flex items-center justify-between flex-wrap gap-4">
              <div>
                <p className="text-sm text-slate-500">Kode Peminjaman</p>
                <p className="font-mono font-bold text-lg text-slate-900">{result.loan_code}</p>
              </div>
              <div>
                <p className="text-sm text-slate-500 mb-1">Status</p>
                <span className={`inline-flex px-3 py-1 rounded-full text-sm font-medium ${statusLabels[result.status]?.className}`}>
                  {statusLabels[result.status]?.text || result.status}
                </span>
              </div>
            </div>

            {/* Peminjam */}
            <div className="flex items-start gap-3">
              <User className="w-5 h-5 text-cyan-600 mt-0.5" />
              <div>
                <p className="text-sm text-slate-500">Peminjam</p>
                <p className="font-medium text-slate-900">{result.borrower_name}</p>
                <p className="text-sm text-slate-500">{result.borrower_email}</p>
                {result.borrower_student_id && <p className="text-sm text-slate-500">NIM: {result.borrower_student_id}</p>}
              </div>
            </div>

            {/* Barang */}
            <div className="flex items-start gap-3">
              <Package className="w-5 h-5 text-cyan-600 mt-0.5" />
              <div>
                <p className="text-sm text-slate-500">Barang</p>
                <p className="font-medium text-slate-900">{result.item?.name} ({result.item?.item_code})</p>
                <p className="text-sm text-slate-500">Jumlah: {result.qty} | Kategori: {result.item?.category}</p>
              </div>
            </div>

            {/* Foto */}
            {result.borrow_photo && (
              <div>
                <p className="text-sm text-slate-500 mb-2">Foto Verifikasi</p>
                <img src={`/storage/${result.borrow_photo}`} alt="Foto" className="w-full max-w-sm h-48 object-cover rounded-lg border border-slate-200" />
              </div>
            )}

            {/* Actions based on status */}
            {result.status === 'pending' && (
              <div className="pt-4 border-t border-slate-200">
                <button onClick={handleApprove} disabled={actionLoading} className="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-2.5 rounded-lg font-medium transition-colors disabled:opacity-50">
                  <CheckCircle className="w-5 h-5" />
                  {actionLoading ? 'Memproses...' : 'Setujui & Serahkan'}
                </button>
              </div>
            )}

            {result.status === 'borrowed' && (
              <div className="pt-4 border-t border-slate-200">
                <h3 className="font-semibold text-slate-900 mb-3 flex items-center gap-2">
                  <Undo2 className="w-5 h-5 text-cyan-600" />
                  Proses Pengembalian
                </h3>
                <form onSubmit={handleReturn} className="space-y-4">
                  <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1">Kondisi Barang</label>
                    <select value={returnForm.condition} onChange={(e) => setReturnForm({ ...returnForm, condition: e.target.value })} className="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 outline-none bg-white" required>
                      <option value="bagus">Bagus</option>
                      <option value="rusak">Rusak</option>
                      <option value="hilang">Hilang</option>
                    </select>
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1">Catatan (Opsional)</label>
                    <textarea value={returnForm.note} onChange={(e) => setReturnForm({ ...returnForm, note: e.target.value })} className="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 outline-none" rows="2" placeholder="Catatan kondisi barang..." />
                  </div>
                  <button type="submit" disabled={actionLoading} className="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-2.5 rounded-lg font-medium transition-colors disabled:opacity-50">
                    <Undo2 className="w-5 h-5" />
                    {actionLoading ? 'Memproses...' : 'Konfirmasi Pengembalian'}
                  </button>
                </form>
              </div>
            )}

            {result.status === 'returned' && (
              <div className="pt-4 border-t border-slate-200">
                <div className="bg-green-50 border border-green-200 rounded-lg p-4 flex items-center gap-2">
                  <CheckCircle className="w-5 h-5 text-green-600" />
                  <p className="text-sm text-green-700 font-medium">Barang sudah dikembalikan.</p>
                </div>
              </div>
            )}

            {/* Action buttons */}
            <div className="flex flex-wrap gap-3 pt-4 border-t border-slate-200">
              <button onClick={() => navigate(`/loans/${result.id}`)} className="inline-flex items-center gap-2 bg-cyan-600 hover:bg-cyan-700 text-white px-6 py-2.5 rounded-lg font-medium transition-colors">
                Lihat Detail
              </button>
              <button onClick={resetResult} className="inline-flex items-center gap-2 bg-white border border-slate-300 text-slate-700 px-6 py-2.5 rounded-lg font-medium hover:bg-slate-50 transition-colors">
                {activeTab === 'scan' ? <ScanLine className="w-4 h-4" /> : activeTab === 'upload' ? <Upload className="w-4 h-4" /> : <KeyRound className="w-4 h-4" />}
                Verifikasi Lagi
              </button>
            </div>
          </div>
        </div>
      ) : (
        <>
          {/* Tabs */}
          <div className="flex gap-2 mb-6 bg-white rounded-xl border border-slate-200 p-2">
            {tabs.map((tab) => (
              <button
                key={tab.id}
                onClick={() => { setActiveTab(tab.id); setError(''); setScanning(false); }}
                className={`flex-1 flex items-center justify-center gap-2 px-4 py-3 rounded-lg text-sm font-medium transition-colors ${
                  activeTab === tab.id ? 'bg-cyan-600 text-white' : 'text-slate-600 hover:bg-slate-50'
                }`}
              >
                <tab.icon className="w-5 h-5" />
                {tab.label}
              </button>
            ))}
          </div>

          {/* Tab content */}
          <div className="bg-white rounded-xl border border-slate-200 p-6">
            {/* Scan QR Tab */}
            {activeTab === 'scan' && (
              <>
                {!scanning && !processing ? (
                  <div className="text-center py-10">
                    <div className="inline-flex items-center justify-center w-20 h-20 bg-cyan-50 rounded-full mb-4">
                      <ScanLine className="w-10 h-10 text-cyan-600" />
                    </div>
                    <p className="text-slate-500 mb-6">Tekan tombol di bawah untuk memindai QR Code</p>
                    <button onClick={() => setScanning(true)} className="inline-flex items-center gap-2 bg-cyan-600 hover:bg-cyan-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors">
                      <ScanLine className="w-5 h-5" />
                      Mulai Scan
                    </button>
                  </div>
                ) : processing ? (
                  <div className="text-center py-10">
                    <div className="inline-flex items-center justify-center w-20 h-20 bg-cyan-50 rounded-full mb-4">
                      <ScanLine className="w-10 h-10 text-cyan-600 animate-pulse" />
                    </div>
                    <p className="text-slate-500">Memproses QR Code...</p>
                  </div>
                ) : (
                  <div>
                    <div id="qr-reader" className="w-full max-w-md mx-auto" />
                    <div className="text-center mt-4">
                      <button onClick={stopScanner} className="inline-flex items-center gap-2 text-slate-500 hover:text-slate-700 px-4 py-2.5">
                        <CameraOff className="w-4 h-4" />
                        Hentikan Scan
                      </button>
                    </div>
                  </div>
                )}
              </>
            )}

            {/* Upload PDF Tab */}
            {activeTab === 'upload' && (
              <div className="text-center py-6">
                <div className="inline-flex items-center justify-center w-20 h-20 bg-cyan-50 rounded-full mb-4">
                  <Upload className="w-10 h-10 text-cyan-600" />
                </div>
                <p className="text-slate-500 mb-6">Upload file PDF bukti peminjaman untuk verifikasi otomatis</p>
                <form onSubmit={handlePdfUpload} className="space-y-4">
                  <div className="border-2 border-dashed border-slate-300 hover:border-cyan-500 rounded-lg p-8 transition-colors">
                    <input ref={fileInputRef} type="file" accept=".pdf" className="hidden" id="pdf-upload" onChange={() => {}} />
                    <label htmlFor="pdf-upload" className="cursor-pointer flex flex-col items-center gap-2">
                      <Upload className="w-8 h-8 text-slate-400" />
                      <span className="text-sm text-slate-600 font-medium">Klik untuk pilih file PDF</span>
                      <span className="text-xs text-slate-400">Format: PDF (max 5MB)</span>
                    </label>
                    {fileInputRef.current?.files[0] && (
                      <p className="text-sm text-cyan-600 mt-2">{fileInputRef.current.files[0].name}</p>
                    )}
                  </div>
                  <button type="submit" disabled={processing} className="inline-flex items-center gap-2 bg-cyan-600 hover:bg-cyan-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors disabled:opacity-50">
                    <Upload className="w-5 h-5" />
                    {processing ? 'Memproses...' : 'Verifikasi PDF'}
                  </button>
                </form>
              </div>
            )}

            {/* Enter Code Tab */}
            {activeTab === 'code' && (
              <div className="text-center py-6">
                <div className="inline-flex items-center justify-center w-20 h-20 bg-cyan-50 rounded-full mb-4">
                  <KeyRound className="w-10 h-10 text-cyan-600" />
                </div>
                <p className="text-slate-500 mb-6">Masukkan kode peminjaman (contoh: PJM-2026-0001)</p>
                <form onSubmit={handleCodeLookup} className="space-y-4 max-w-md mx-auto">
                  <input
                    type="text"
                    value={loanCode}
                    onChange={(e) => setLoanCode(e.target.value.toUpperCase())}
                    className="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 outline-none text-center font-mono text-lg tracking-wider"
                    placeholder="PJM-2026-0001"
                    required
                  />
                  <button type="submit" disabled={processing} className="inline-flex items-center gap-2 bg-cyan-600 hover:bg-cyan-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors disabled:opacity-50 w-full justify-center">
                    <KeyRound className="w-5 h-5" />
                    {processing ? 'Mencari...' : 'Cari Transaksi'}
                  </button>
                </form>
              </div>
            )}
          </div>
        </>
      )}
    </div>
  )
}

export default ScanQR