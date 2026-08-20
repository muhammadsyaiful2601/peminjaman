import { useEffect, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import api from '../api/axios'
import {
  ArrowLeft,
  Camera,
  CameraOff,
  RefreshCw,
  Package,
  Send,
  User,
  Mail,
  Phone,
  IdCard,
  CheckCircle,
  Plus,
} from 'lucide-react'

function NewLoan() {
  const videoRef = useRef(null)
  const streamRef = useRef(null)

  const [items, setItems] = useState([])
  const [loanItems, setLoanItems] = useState([{ item_id: '', qty: 1 }])
  const [cameraActive, setCameraActive] = useState(false)
  const [photo, setPhoto] = useState(null)
  const [photoError, setPhotoError] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState('')
  const [successLoan, setSuccessLoan] = useState(null)
  const [loadingItems, setLoadingItems] = useState(true)

  // Borrower form data
  const [borrowerName, setBorrowerName] = useState('')
  const [borrowerEmail, setBorrowerEmail] = useState('')
  const [borrowerPhone, setBorrowerPhone] = useState('')
  const [borrowerStudentId, setBorrowerStudentId] = useState('')

  useEffect(() => {
    const fetchItems = async () => {
      try {
        const response = await api.get('/items?per_page=100')
        setItems(response.data.data || [])
      } catch (err) {
        setError('Gagal memuat daftar barang')
      } finally {
        setLoadingItems(false)
      }
    }
    fetchItems()

    return () => {
      stopCamera()
    }
  }, [])

  const stopCamera = () => {
    if (streamRef.current) {
      streamRef.current.getTracks().forEach((track) => track.stop())
      streamRef.current = null
    }
    setCameraActive(false)
  }

  const startCamera = () => {
    setPhotoError('')
    setCameraActive(true)
  }

  // Start camera stream when cameraActive becomes true (after video element is in DOM)
  useEffect(() => {
    if (!cameraActive) return

    let cancelled = false

    const startStream = async () => {
      try {
        const stream = await navigator.mediaDevices.getUserMedia({
          video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
        })
        if (cancelled) {
          stream.getTracks().forEach((track) => track.stop())
          return
        }
        streamRef.current = stream
        if (videoRef.current) {
          videoRef.current.srcObject = stream
          await videoRef.current.play()
        }
      } catch (err) {
        if (!cancelled) {
          setPhotoError('Tidak dapat mengakses kamera. Pastikan izin kamera diberikan dan gunakan HTTPS atau localhost.')
          setCameraActive(false)
        }
      }
    }

    startStream()

    return () => {
      cancelled = true
      if (streamRef.current) {
        streamRef.current.getTracks().forEach((track) => track.stop())
        streamRef.current = null
      }
    }
  }, [cameraActive])

  const capturePhoto = () => {
    if (!videoRef.current) return
    const canvas = document.createElement('canvas')
    canvas.width = videoRef.current.videoWidth
    canvas.height = videoRef.current.videoHeight
    const ctx = canvas.getContext('2d')
    ctx.drawImage(videoRef.current, 0, 0)
    canvas.toBlob((blob) => {
      const filename = `borrow-photo-${Date.now()}.jpg`
      const file = new File([blob], filename, { type: 'image/jpeg' })
      setPhoto(file)
      setPhotoError('')
      stopCamera()
    }, 'image/jpeg', 0.9)
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    setError('')

    if (!photo) {
      setPhotoError('Wajib mengambil foto peminjam sebagai verifikasi identitas visual.')
      return
    }

    if (loanItems.some((loanItem) => !loanItem.item_id)) {
      setError('Silakan pilih semua barang yang akan dipinjam.')
      return
    }

    if (!borrowerName || !borrowerEmail) {
      setError('Nama dan email peminjam wajib diisi.')
      return
    }

    setSubmitting(true)
    const formData = new FormData()
    loanItems.forEach((loanItem, index) => {
      formData.append(`items[${index}][item_id]`, loanItem.item_id)
      formData.append(`items[${index}][qty]`, loanItem.qty)
    })
    formData.append('borrower_name', borrowerName)
    formData.append('borrower_email', borrowerEmail)
    if (borrowerPhone) formData.append('borrower_phone', borrowerPhone)
    if (borrowerStudentId) formData.append('borrower_student_id', borrowerStudentId)
    formData.append('borrow_photo', photo)

    try {
      const response = await api.post('/loans', formData)
      setSuccessLoan(response.data.loan)
    } catch (err) {
      const data = err.response?.data
      if (data?.errors) {
        setError(Object.values(data.errors).flat().join(', '))
      } else {
        setError(data?.message || 'Gagal membuat peminjaman')
      }
    } finally {
      setSubmitting(false)
    }
  }

  const handleNewLoan = () => {
    // Reset all form state
    setSuccessLoan(null)
    setLoanItems([{ item_id: '', qty: 1 }])
    setPhoto(null)
    setError('')
    setPhotoError('')
    setBorrowerName('')
    setBorrowerEmail('')
    setBorrowerPhone('')
    setBorrowerStudentId('')
  }

  // Success page after loan is created
  if (successLoan) {
    return (
      <div className="max-w-2xl mx-auto">
        <div className="bg-white rounded-xl border border-slate-200 p-8 text-center">
          <div className="inline-flex items-center justify-center w-20 h-20 bg-emerald-100 rounded-full mb-6">
            <CheckCircle className="w-12 h-12 text-emerald-600" />
          </div>

          <h1 className="text-2xl font-bold text-slate-900 mb-3">Peminjaman Berhasil Dibuat!</h1>
          <p className="text-slate-500 mb-8">
            Barang telah langsung diserahkan kepada peminjam (status: Dipinjam). QR Code transaksi telah
            dikirim ke email peminjam dan digunakan untuk verifikasi pengembalian barang.
          </p>

          {/* Loan summary */}
          <div className="bg-slate-50 rounded-lg p-6 text-left mb-6">
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div className="sm:col-span-2 text-center mb-2">
                <p className="text-sm text-slate-500">Kode Peminjaman</p>
                <p className="font-mono font-bold text-2xl text-cyan-600 tracking-wider">{successLoan.loan_code}</p>
              </div>
              <div>
                <p className="text-sm text-slate-500">Peminjam</p>
                <p className="font-medium text-slate-900">{successLoan.borrower_name}</p>
              </div>
              <div>
                <p className="text-sm text-slate-500">Email</p>
                <p className="font-medium text-slate-900 text-sm">{successLoan.borrower_email}</p>
              </div>
              <div className="sm:col-span-2">
                <p className="text-sm text-slate-500 mb-1">Barang yang dipinjam</p>
                <div className="space-y-1">
                  {(successLoan.loan_items?.length ? successLoan.loan_items : [{ item: successLoan.item, qty: successLoan.qty }]).map((loanItem) => (
                    <p key={loanItem.item?.id} className="font-medium text-slate-900">
                      {loanItem.item?.name} <span className="text-slate-500">({loanItem.qty} unit)</span>
                    </p>
                  ))}
                </div>
              </div>
            </div>
          </div>

          {/* Email notification info */}
          <div className="bg-cyan-50 border border-cyan-200 rounded-lg p-4 mb-6 text-left">
            <div className="flex items-start gap-3">
              <Mail className="w-5 h-5 text-cyan-600 mt-0.5 shrink-0" />
              <div>
                <p className="text-sm font-medium text-cyan-900">QR Code Terkirim via Email</p>
                <p className="text-xs text-cyan-700 mt-1">
                  Email berisi QR Code telah dikirim ke <strong>{successLoan.borrower_email}</strong>.
                  Saat pengembalian, peminjam menunjukkan email/QR ini kepada petugas untuk verifikasi pengembalian barang.
                </p>
              </div>
            </div>
          </div>

          {/* Action buttons */}
          <div className="flex flex-col sm:flex-row gap-3 justify-center">
            <button
              onClick={handleNewLoan}
              className="inline-flex items-center gap-2 bg-cyan-600 hover:bg-cyan-700 text-white px-6 py-2.5 rounded-lg font-medium transition-colors justify-center"
            >
              <Plus className="w-5 h-5" />
              Buat Peminjaman Lagi
            </button>
            <Link
              to="/loans"
              className="inline-flex items-center gap-2 bg-white border border-slate-300 text-slate-700 px-6 py-2.5 rounded-lg font-medium hover:bg-slate-50 transition-colors justify-center"
            >
              <ArrowLeft className="w-4 h-4" />
              Lihat Daftar Peminjaman
            </Link>
          </div>
        </div>
      </div>
    )
  }

  return (
    <div className="max-w-3xl mx-auto">
      <div className="mb-6 flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">Buat Peminjaman Baru</h1>
          <p className="text-slate-500 mt-1">
            Petugas mendaftarkan peminjaman untuk mahasiswa & kirim QR via email
          </p>
        </div>
        <Link to="/loans" className="inline-flex items-center gap-2 text-slate-500 hover:text-slate-700">
          <ArrowLeft className="w-4 h-4" />
          Kembali
        </Link>
      </div>

      <form onSubmit={handleSubmit} className="space-y-6">
        {error && (
          <div className="bg-red-50 border border-red-200 text-red-600 text-sm rounded-lg px-4 py-3">
            {error}
          </div>
        )}

        {/* Step 1: Pilih barang */}
        <div className="bg-white rounded-xl border border-slate-200 p-6">
          <h2 className="font-semibold text-slate-900 mb-4 flex items-center gap-2">
            <Package className="w-5 h-5 text-cyan-600" />
            1. Pilih Barang
          </h2>

          {loadingItems ? (
            <p className="text-slate-500">Memuat daftar barang...</p>
          ) : (
            <div className="space-y-3">
              {loanItems.map((loanItem, index) => (
                <div key={index} className="grid grid-cols-1 sm:grid-cols-[1fr_140px_auto] gap-3 items-end">
                  <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1">Barang {index + 1}</label>
                    <select
                      value={loanItem.item_id}
                      onChange={(e) => setLoanItems((current) => current.map((item, itemIndex) => itemIndex === index ? { ...item, item_id: e.target.value } : item))}
                      className="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 outline-none bg-white"
                      required
                    >
                      <option value="">Pilih barang...</option>
                      {items.filter((item) => item.stock > 0).map((item) => (
                        <option key={item.id} value={item.id}>
                          {item.name} (Stok: {item.stock})
                        </option>
                      ))}
                    </select>
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1">Jumlah</label>
                    <input
                      type="number"
                      min="1"
                      value={loanItem.qty}
                      onChange={(e) => setLoanItems((current) => current.map((item, itemIndex) => itemIndex === index ? { ...item, qty: Math.max(1, parseInt(e.target.value) || 1) } : item))}
                      className="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 outline-none"
                      required
                    />
                  </div>
                  {loanItems.length > 1 && (
                    <button type="button" onClick={() => setLoanItems((current) => current.filter((_, itemIndex) => itemIndex !== index))} className="px-3 py-2.5 text-red-600 hover:text-red-700 text-sm font-medium">Hapus</button>
                  )}
                </div>
              ))}
              <button type="button" onClick={() => setLoanItems((current) => [...current, { item_id: '', qty: 1 }])} className="inline-flex items-center gap-2 text-cyan-600 hover:text-cyan-700 text-sm font-medium">
                <Plus className="w-4 h-4" /> Tambah barang lain
              </button>
            </div>
          )}
        </div>

        {/* Step 2: Data Peminjam */}
        <div className="bg-white rounded-xl border border-slate-200 p-6">
          <h2 className="font-semibold text-slate-900 mb-4 flex items-center gap-2">
            <User className="w-5 h-5 text-cyan-600" />
            2. Data Peminjam (Mahasiswa)
          </h2>
          <p className="text-sm text-slate-500 mb-4">
            Mahasiswa mengisi data langsung di komputer petugas. QR Code akan dikirim ke email yang dimasukkan.
          </p>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-slate-700 mb-1">Nama Lengkap *</label>
              <div className="relative">
                <User className="w-5 h-5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                <input
                  type="text"
                  value={borrowerName}
                  onChange={(e) => setBorrowerName(e.target.value)}
                  className="w-full pl-10 pr-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 outline-none"
                  placeholder="Nama mahasiswa"
                  required
                />
              </div>
            </div>
            <div>
              <label className="block text-sm font-medium text-slate-700 mb-1">Email *</label>
              <div className="relative">
                <Mail className="w-5 h-5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                <input
                  type="email"
                  value={borrowerEmail}
                  onChange={(e) => setBorrowerEmail(e.target.value)}
                  className="w-full pl-10 pr-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 outline-none"
                  placeholder="email@kampus.ac.id"
                  required
                />
              </div>
            </div>
            <div>
              <label className="block text-sm font-medium text-slate-700 mb-1">No. Telepon (Opsional)</label>
              <div className="relative">
                <Phone className="w-5 h-5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                <input
                  type="tel"
                  value={borrowerPhone}
                  onChange={(e) => setBorrowerPhone(e.target.value)}
                  className="w-full pl-10 pr-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 outline-none"
                  placeholder="08xxxxxxxxxx"
                />
              </div>
            </div>
            <div>
              <label className="block text-sm font-medium text-slate-700 mb-1">NIM / NIP (Opsional)</label>
              <div className="relative">
                <IdCard className="w-5 h-5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                <input
                  type="text"
                  value={borrowerStudentId}
                  onChange={(e) => setBorrowerStudentId(e.target.value)}
                  className="w-full pl-10 pr-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 outline-none"
                  placeholder="Nomor induk mahasiswa"
                />
              </div>
            </div>
          </div>
        </div>

        {/* Step 3: Foto verifikasi */}
        <div className="bg-white rounded-xl border border-slate-200 p-6">
          <h2 className="font-semibold text-slate-900 mb-4 flex items-center gap-2">
            <Camera className="w-5 h-5 text-cyan-600" />
            3. Foto Verifikasi Peminjam
          </h2>
          <p className="text-sm text-slate-500 mb-4">
            Petugas memfoto wajah mahasiswa yang meminjam sebagai bukti identitas visual.
          </p>

          {photoError && (
            <div className="bg-red-50 border border-red-200 text-red-600 text-sm rounded-lg px-4 py-3 mb-4">
              {photoError}
            </div>
          )}

          {photo ? (
            <div className="space-y-3">
              <img
                src={URL.createObjectURL(photo)}
                alt="Foto verifikasi peminjam"
                className="w-full max-w-md h-64 object-cover rounded-lg border border-slate-200"
              />
              <button
                type="button"
                onClick={() => setPhoto(null)}
                className="inline-flex items-center gap-2 text-red-600 font-medium text-sm hover:text-red-700"
              >
                <RefreshCw className="w-4 h-4" />
                Ambil ulang foto
              </button>
            </div>
          ) : cameraActive ? (
            <div className="space-y-3">
              <video
                ref={videoRef}
                className="w-full max-w-md h-64 object-cover rounded-lg border border-slate-200 bg-black"
              />
              <div className="flex gap-3">
                <button
                  type="button"
                  onClick={capturePhoto}
                  className="inline-flex items-center gap-2 bg-cyan-600 hover:bg-cyan-700 text-white px-4 py-2.5 rounded-lg font-medium transition-colors"
                >
                  <Camera className="w-5 h-5" />
                  Ambil Foto
                </button>
                <button
                  type="button"
                  onClick={stopCamera}
                  className="inline-flex items-center gap-2 text-slate-500 hover:text-slate-700 px-4 py-2.5"
                >
                  <CameraOff className="w-4 h-4" />
                  Matikan Kamera
                </button>
              </div>
            </div>
          ) : (
            <button
              type="button"
              onClick={startCamera}
              className="inline-flex items-center gap-2 bg-white border-2 border-dashed border-slate-300 hover:border-cyan-500 hover:text-cyan-600 text-slate-500 px-6 py-4 rounded-lg font-medium transition-colors w-full max-w-md justify-center"
            >
              <Camera className="w-6 h-6" />
              Nyalakan Kamera
            </button>
          )}
        </div>

        <button
          type="submit"
          disabled={submitting}
          className="inline-flex items-center gap-2 bg-cyan-600 hover:bg-cyan-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors disabled:opacity-50 disabled:cursor-not-allowed w-full sm:w-auto justify-center"
        >
          <Send className="w-5 h-5" />
          {submitting ? 'Memproses...' : 'Buat Peminjaman & Kirim QR'}
        </button>
      </form>
    </div>
  )
}

export default NewLoan