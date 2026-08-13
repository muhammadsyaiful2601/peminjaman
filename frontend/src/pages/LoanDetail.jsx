import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import api from '../api/axios'
import CameraCapture, { dataURLtoFile } from '../components/CameraCapture'
import {
  ArrowLeft,
  Undo2,
  User,
  Package,
  Calendar,
  ShieldCheck,
  Image,
  Mail,
  Camera,
  RefreshCw,
} from 'lucide-react'

function LoanDetail() {
  const { id } = useParams()
  const { user } = useAuth()
  const [loan, setLoan] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [actionLoading, setActionLoading] = useState(false)
  const [returnForm, setReturnForm] = useState({ condition: 'bagus', note: '' })
  const [returnPhoto, setReturnPhoto] = useState(null)

  const isStaff = user?.role === 'admin' || user?.role === 'assistant'

  const fetchLoan = async () => {
    try {
      const response = await api.get(`/loans/${id}`)
      setLoan(response.data.loan)
    } catch (err) {
      setError('Gagal memuat detail peminjaman')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    fetchLoan()
  }, [id])

  const handleReturn = async (e) => {
    e.preventDefault()
    if (!returnPhoto) {
      alert('Ambil foto bukti pengembalian terlebih dahulu.')
      return
    }
    if (!window.confirm('Konfirmasi pengembalian barang ini? Bukti akan dikirim ke email peminjam.')) return
    setActionLoading(true)
    try {
      const formData = new FormData()
      formData.append('condition_on_return', returnForm.condition)
      if (returnForm.note) formData.append('condition_note', returnForm.note)
      formData.append('return_photo', dataURLtoFile(returnPhoto, 'return-proof.jpg'))
      await api.post(`/loans/${id}/return`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      fetchLoan()
      setReturnPhoto(null)
      setReturnForm({ condition: 'bagus', note: '' })
    } catch (err) {
      alert(err.response?.data?.message || 'Gagal memproses pengembalian')
    } finally {
      setActionLoading(false)
    }
  }

  if (loading) {
    return <div className="text-center py-12 text-slate-500">Memuat data...</div>
  }

  if (error || !loan) {
    return (
      <div className="text-center py-12">
        <p className="text-slate-500 mb-4">{error || 'Data tidak ditemukan'}</p>
        <Link to="/loans" className="text-cyan-600 font-medium hover:underline">
          Kembali ke daftar peminjaman
        </Link>
      </div>
    )
  }

  const statusInfo = {
    pending: { text: 'Menunggu Verifikasi', className: 'bg-amber-100 text-amber-700' },
    borrowed: { text: 'Dipinjam', className: 'bg-emerald-100 text-emerald-700' },
    returned: { text: 'Dikembalikan', className: 'bg-green-100 text-green-700' },
    rejected: { text: 'Ditolak', className: 'bg-red-100 text-red-700' },
  }

  const status = statusInfo[loan.status] || { text: loan.status, className: 'bg-gray-100 text-gray-700' }

  const formatDate = (date) => {
    if (!date) return '-'
    return new Date(date).toLocaleString('id-ID', {
      day: 'numeric',
      month: 'long',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
  }

  return (
    <div className="max-w-5xl mx-auto">
      <div className="mb-6 flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">Detail Peminjaman</h1>
          <div className="flex items-center gap-3 mt-1">
            <p className="font-mono text-xs text-slate-500">UUID: {loan.uuid}</p>
            <span className="inline-flex px-2 py-0.5 rounded-full text-xs font-mono font-bold bg-slate-900 text-cyan-400">
              {loan.loan_code}
            </span>
          </div>
        </div>
        <Link to="/loans" className="inline-flex items-center gap-2 text-slate-500 hover:text-slate-700">
          <ArrowLeft className="w-4 h-4" />
          Kembali
        </Link>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Left column - Status & info */}
        <div className="space-y-6">
          <div className="bg-white rounded-xl border border-slate-200 p-6">
            <h2 className="font-semibold text-slate-900 mb-3">Status</h2>
            <span className={`inline-flex px-3 py-1 rounded-full text-sm font-medium ${status.className}`}>
              {status.text}
            </span>
          </div>

          <div className="bg-cyan-50 border border-cyan-200 rounded-xl p-6">
            <div className="flex items-start gap-3">
              <Mail className="w-5 h-5 text-cyan-600 mt-0.5 shrink-0" />
              <div>
                <h2 className="font-semibold text-cyan-900 text-sm mb-1">QR Code via Email</h2>
                <p className="text-xs text-cyan-700 leading-relaxed">
                  QR Code transaksi telah dikirim ke email peminjam: <strong>{loan.borrower_email}</strong>.
                  Saat pengembalian, peminjam menunjukkan email/QR ini kepada petugas untuk verifikasi pengembalian barang.
                </p>
              </div>
            </div>
          </div>
        </div>

        {/* Right column - Details */}
        <div className="lg:col-span-2 space-y-6">
          {/* Informasi Peminjam */}
          <div className="bg-white rounded-xl border border-slate-200 p-6">
            <h2 className="font-semibold text-slate-900 mb-4 flex items-center gap-2">
              <User className="w-5 h-5 text-cyan-600" />
              Informasi Peminjam
            </h2>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <p className="text-sm text-slate-500">Nama</p>
                <p className="font-medium text-slate-900">{loan.borrower_name}</p>
              </div>
              <div>
                <p className="text-sm text-slate-500">Email</p>
                <p className="font-medium text-slate-900">{loan.borrower_email}</p>
              </div>
              {loan.borrower_phone && (
                <div>
                  <p className="text-sm text-slate-500">No. Telepon</p>
                  <p className="font-medium text-slate-900">{loan.borrower_phone}</p>
                </div>
              )}
              {loan.borrower_student_id && (
                <div>
                  <p className="text-sm text-slate-500">NIM / NIP</p>
                  <p className="font-medium text-slate-900">{loan.borrower_student_id}</p>
                </div>
              )}
            </div>
          </div>

          {/* Informasi Barang */}
          <div className="bg-white rounded-xl border border-slate-200 p-6">
            <h2 className="font-semibold text-slate-900 mb-4 flex items-center gap-2">
              <Package className="w-5 h-5 text-cyan-600" />
              Informasi Barang
            </h2>
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
              <div>
                <p className="text-sm text-slate-500">Nama Barang</p>
                <p className="font-medium text-slate-900">{loan.item?.name}</p>
              </div>
              <div>
                <p className="text-sm text-slate-500">Kode</p>
                <p className="font-medium text-slate-900">{loan.item?.item_code}</p>
              </div>
              <div>
                <p className="text-sm text-slate-500">Kategori</p>
                <p className="font-medium text-slate-900">{loan.item?.category}</p>
              </div>
              <div>
                <p className="text-sm text-slate-500">Jumlah</p>
                <p className="font-medium text-slate-900">{loan.qty}</p>
              </div>
            </div>
          </div>

          {/* Foto Verifikasi */}
          {loan.borrow_photo && (
            <div className="bg-white rounded-xl border border-slate-200 p-6">
              <h2 className="font-semibold text-slate-900 mb-4 flex items-center gap-2">
                <Image className="w-5 h-5 text-cyan-600" />
                Foto Verifikasi Peminjam
              </h2>
              <img
                src={`/storage/${loan.borrow_photo}`}
                alt="Foto verifikasi peminjam"
                className="w-full max-w-md h-64 object-cover rounded-lg border border-slate-200"
              />
            </div>
          )}

          {/* Timeline */}
          <div className="bg-white rounded-xl border border-slate-200 p-6">
            <h2 className="font-semibold text-slate-900 mb-4 flex items-center gap-2">
              <Calendar className="w-5 h-5 text-cyan-600" />
              Timeline Transaksi
            </h2>
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <div>
                <p className="text-sm text-slate-500">Dibuat</p>
                <p className="font-medium text-slate-900 text-sm">{formatDate(loan.created_at)}</p>
              </div>
              <div>
                <p className="text-sm text-slate-500">Diserahkan</p>
                <p className="font-medium text-slate-900 text-sm">{formatDate(loan.borrowed_at)}</p>
              </div>
              <div>
                <p className="text-sm text-slate-500">Dikembalikan</p>
                <p className="font-medium text-slate-900 text-sm">{formatDate(loan.returned_at)}</p>
              </div>
            </div>

            {loan.verified_by && (
              <div className="mt-4 pt-4 border-t border-slate-200 flex items-center gap-2">
                <ShieldCheck className="w-4 h-4 text-emerald-600" />
                <p className="text-sm text-slate-600">
                  Diverifikasi oleh: <span className="font-medium text-slate-900">{loan.verifier?.name}</span>
                </p>
              </div>
            )}

            {loan.condition_on_return && (
              <div className="mt-4 pt-4 border-t border-slate-200">
                <p className="text-sm text-slate-500">Kondisi Barang Saat Dikembalikan</p>
                <p className="font-medium text-slate-900">{loan.condition_on_return}</p>
              </div>
            )}
          </div>

          {/* Aksi untuk staff */}
          {isStaff && loan.status === 'borrowed' && (
            <div className="bg-white rounded-xl border border-slate-200 p-6">
              <h2 className="font-semibold text-slate-900 mb-4 flex items-center gap-2">
                <Undo2 className="w-5 h-5 text-cyan-600" />
                Proses Pengembalian
              </h2>
              {!returnPhoto ? (
                <div className="space-y-3">
                  <p className="text-sm text-slate-500">
                    Ambil foto bukti barang yang dikembalikan, lalu isi formulir bukti barang diterima.
                    Bukti akan dikirim ke email peminjam.
                  </p>
                  <CameraCapture onCapture={setReturnPhoto} />
                </div>
              ) : (
                <form onSubmit={handleReturn} className="space-y-4">
                  <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1">Foto Bukti Pengembalian</label>
                    <img src={returnPhoto} alt="Bukti" className="w-full max-w-sm mx-auto h-40 object-cover rounded-lg border border-slate-200" />
                    <button
                      type="button"
                      onClick={() => setReturnPhoto(null)}
                      className="mt-2 text-sm text-cyan-600 hover:underline"
                    >
                      Ambil Ulang Foto
                    </button>
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1">Kondisi Barang</label>
                    <select
                      value={returnForm.condition}
                      onChange={(e) => setReturnForm({ ...returnForm, condition: e.target.value })}
                      className="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 outline-none bg-white"
                      required
                    >
                      <option value="bagus">Bagus</option>
                      <option value="rusak">Rusak</option>
                      <option value="hilang">Hilang</option>
                    </select>
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1">
                      Catatan (Opsional)
                    </label>
                    <textarea
                      value={returnForm.note}
                      onChange={(e) => setReturnForm({ ...returnForm, note: e.target.value })}
                      className="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 outline-none"
                      rows="2"
                      placeholder="Catatan kondisi barang jika ada..."
                    />
                  </div>
                  <button
                    type="submit"
                    disabled={actionLoading}
                    className="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-2.5 rounded-lg font-medium transition-colors disabled:opacity-50"
                  >
                    <Undo2 className="w-5 h-5" />
                    <Mail className="w-4 h-4" />
                    {actionLoading ? 'Memproses...' : 'Konfirmasi & Kirim ke Peminjam'}
                  </button>
                </form>
              )}
            </div>
          )}
        </div>
      </div>
    </div>
  )
}

export default LoanDetail