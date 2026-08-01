import { useEffect, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Html5Qrcode } from 'html5-qrcode'
import api from '../api/axios'
import { ScanLine, CameraOff, CheckCircle, XCircle, Package, User } from 'lucide-react'

function ScanQR() {
  const navigate = useNavigate()
  const scannerRef = useRef(null)
  const [scanning, setScanning] = useState(false)
  const [error, setError] = useState('')
  const [result, setResult] = useState(null)
  const [processing, setProcessing] = useState(false)

  // Start scanner when scanning becomes true (qr-reader div is in DOM)
  useEffect(() => {
    if (!scanning) return

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
            // Got result, stop scanner
            if (cancelled) return
            cancelled = true

            try {
              await scanner.stop()
            } catch (e) {
              // ignore
            }
            scanner.clear()
            scannerRef.current = null
            setScanning(false)
            setProcessing(true)

            // Check if the decoded text is a valid loan UUID
            const uuidPattern = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i
            if (!uuidPattern.test(decodedText.trim())) {
              setError('QR Code tidak valid. Pastikan ini adalah QR Code transaksi peminjaman.')
              setProcessing(false)
              return
            }

            // Try to load loan by UUID
            try {
              const response = await api.get(`/loans/qr/${decodedText.trim()}`)
              setResult(response.data.loan)
              setProcessing(false)
            } catch (err) {
              setError(err.response?.data?.message || 'Transaksi tidak ditemukan')
              setProcessing(false)
            }
          },
          (errorMessage) => {
            // Ignore scan errors (continuous scanning)
          }
        )
      } catch (err) {
        if (!cancelled) {
          setError('Tidak dapat mengakses kamera. Pastikan izin kamera diberikan dan gunakan HTTPS atau localhost.')
          setScanning(false)
        }
      }
    }

    // Small delay to ensure DOM element is ready
    const timer = setTimeout(startScanner, 100)

    return () => {
      cancelled = true
      clearTimeout(timer)
      if (scannerRef.current) {
        try {
          scannerRef.current.stop().then(() => {
            scannerRef.current?.clear()
          }).catch(() => {})
        } catch (e) {
          // ignore
        }
        scannerRef.current = null
      }
    }
  }, [scanning])

  const stopScanner = async () => {
    if (scannerRef.current) {
      try {
        await scannerRef.current.stop()
        scannerRef.current.clear()
      } catch (e) {
        // ignore
      }
      scannerRef.current = null
    }
    setScanning(false)
  }

  // Cleanup on unmount
  useEffect(() => {
    return () => {
      if (scannerRef.current) {
        scannerRef.current.stop().catch(() => {})
      }
    }
  }, [])

  const statusLabels = {
    pending: { text: 'Menunggu', className: 'bg-amber-100 text-amber-700' },
    borrowed: { text: 'Dipinjam', className: 'bg-emerald-100 text-emerald-700' },
    returned: { text: 'Dikembalikan', className: 'bg-green-100 text-green-700' },
    rejected: { text: 'Ditolak', className: 'bg-red-100 text-red-700' },
  }

  return (
    <div className="max-w-3xl mx-auto">
      <div className="mb-8">
        <h1 className="text-2xl font-bold text-slate-900">Scan QR Code</h1>
        <p className="text-slate-500 mt-1">
          Pindai QR Code transaksi untuk verifikasi peminjaman & pengembalian barang
        </p>
      </div>

      {error && (
        <div className="mb-6 bg-red-50 border border-red-200 text-red-600 text-sm rounded-lg px-4 py-3 flex items-start gap-2">
          <XCircle className="w-5 h-5 shrink-0 mt-0.5" />
          <div className="flex-1">
            {error}
            <button
              onClick={() => setError('')}
              className="block mt-2 text-red-700 font-medium hover:underline"
            >
              Tutup
            </button>
          </div>
        </div>
      )}

      {/* Scanner / Result */}
      {!result && (
        <div className="bg-white rounded-xl border border-slate-200 p-6">
          {!scanning && !processing ? (
            <div className="text-center py-10">
              <div className="inline-flex items-center justify-center w-20 h-20 bg-cyan-50 rounded-full mb-4">
                <ScanLine className="w-10 h-10 text-cyan-600" />
              </div>
              <p className="text-slate-500 mb-6">
                Tekan tombol di bawah untuk memindai QR Code transaksi peminjaman
              </p>
              <button
                onClick={() => {
                  setError('')
                  setResult(null)
                  setScanning(true)
                }}
                className="inline-flex items-center gap-2 bg-cyan-600 hover:bg-cyan-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors"
              >
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
              {/* qr-reader div must always be in DOM when scanning is true */}
              <div id="qr-reader" className="w-full max-w-md mx-auto" />
              <div className="text-center mt-4">
                <button
                  onClick={stopScanner}
                  className="inline-flex items-center gap-2 text-slate-500 hover:text-slate-700 px-4 py-2.5"
                >
                  <CameraOff className="w-4 h-4" />
                  Hentikan Scan
                </button>
              </div>
            </div>
          )}
        </div>
      )}

      {/* Result */}
      {result && (
        <div className="bg-white rounded-xl border border-slate-200 overflow-hidden">
          <div className="px-6 py-4 bg-emerald-50 border-b border-emerald-200 flex items-center gap-2">
            <CheckCircle className="w-5 h-5 text-emerald-600" />
            <h2 className="font-semibold text-emerald-700">QR Code Teridentifikasi</h2>
          </div>

          <div className="p-6 space-y-6">
            {/* Status */}
            <div>
              <p className="text-sm text-slate-500 mb-2">Status</p>
              <span
                className={`inline-flex px-3 py-1 rounded-full text-sm font-medium ${
                  statusLabels[result.status]?.className || 'bg-gray-100 text-gray-700'
                }`}
              >
                {statusLabels[result.status]?.text || result.status}
              </span>
            </div>

            {/* Peminjam */}
            <div className="flex items-start gap-3">
              <User className="w-5 h-5 text-cyan-600 mt-0.5" />
              <div>
                <p className="text-sm text-slate-500">Peminjam</p>
                <p className="font-medium text-slate-900">{result.borrower_name}</p>
                <p className="text-sm text-slate-500">{result.borrower_email}</p>
                {result.borrower_student_id && (
                  <p className="text-sm text-slate-500">NIM: {result.borrower_student_id}</p>
                )}
              </div>
            </div>

            {/* Barang */}
            <div className="flex items-start gap-3">
              <Package className="w-5 h-5 text-cyan-600 mt-0.5" />
              <div>
                <p className="text-sm text-slate-500">Barang</p>
                <p className="font-medium text-slate-900">
                  {result.item?.name} ({result.item?.item_code})
                </p>
                <p className="text-sm text-slate-500">
                  Jumlah: {result.qty} | Kategori: {result.item?.category}
                </p>
              </div>
            </div>

            {/* Foto */}
            {result.borrow_photo && (
              <div>
                <p className="text-sm text-slate-500 mb-2">Foto Verifikasi</p>
                <img
                  src={`/storage/${result.borrow_photo}`}
                  alt="Foto verifikasi peminjam"
                  className="w-full max-w-sm h-48 object-cover rounded-lg border border-slate-200"
                />
              </div>
            )}

            {/* Action buttons */}
            <div className="flex flex-wrap gap-3 pt-4 border-t border-slate-200">
              <button
                onClick={() => navigate(`/loans/${result.id}`)}
                className="inline-flex items-center gap-2 bg-cyan-600 hover:bg-cyan-700 text-white px-6 py-2.5 rounded-lg font-medium transition-colors"
              >
                Lihat Detail
              </button>
              <button
                onClick={() => {
                  setResult(null)
                  setError('')
                  setScanning(true)
                }}
                className="inline-flex items-center gap-2 bg-white border border-slate-300 text-slate-700 px-6 py-2.5 rounded-lg font-medium hover:bg-slate-50 transition-colors"
              >
                <ScanLine className="w-4 h-4" />
                Scan Lagi
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}

export default ScanQR