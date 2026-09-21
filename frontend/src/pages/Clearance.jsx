import { useCallback, useEffect, useState } from 'react'
import { useLocation, useNavigate, useSearchParams } from 'react-router-dom'
import api from '../api/axios'
import { downloadBlob } from '../utils/downloadBlob'
import {
  AlertTriangle,
  ArrowLeft,
  BadgeCheck,
  Download,
  FileCheck2,
  History,
  RefreshCw,
  Search,
  ShieldAlert,
  UserRoundSearch,
} from 'lucide-react'

// Pilihan keperluan yang umum diminta jurusan/perpustakaan.
const purposeOptions = [
  'Persyaratan bebas pustaka',
  'Persyaratan pengambilan ijazah',
  'Persyaratan yudisium / wisuda',
  'Persyaratan pindah / cuti studi',
  'Persyaratan Kerja Praktik / magang',
]

const emptyLetter = () => ({
  purpose: '',
  letter_date: new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10),
  laboratory: '',
  signatory_name: '',
  signatory_nip: '',
})

const formatDate = (value) => (value ? new Date(value).toLocaleDateString('id-ID', {
  timeZone: 'Asia/Jakarta',
  day: 'numeric',
  month: 'short',
  year: 'numeric',
}) : '-')

function Clearance() {
  const navigate = useNavigate()
  const location = useLocation()
  const [searchParams] = useSearchParams()
  const isDetailPage = location.pathname === '/clearance/detail'
  const [searchInput, setSearchInput] = useState('')
  const [borrowers, setBorrowers] = useState([])
  const [searching, setSearching] = useState(true)
  const [selected, setSelected] = useState(null)
  const [detail, setDetail] = useState(null)
  const [loadingDetail, setLoadingDetail] = useState(false)
  const [technicians, setTechnicians] = useState([])
  const [letter, setLetter] = useState(emptyLetter)
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')

  const loadBorrowers = useCallback(async (search = '') => {
    setSearching(true)
    setError('')
    try {
      const response = await api.get('/loans/clearance/borrowers', { params: search ? { search } : {} })
      setBorrowers(response.data.data || [])
    } catch (requestError) {
      setError(requestError.response?.data?.message || 'Daftar peminjam gagal dimuat.')
      setBorrowers([])
    } finally {
      setSearching(false)
    }
  }, [])

  useEffect(() => {
    loadBorrowers()
    api.get('/technicians').then((response) => setTechnicians(response.data || [])).catch(() => {})
  }, [loadBorrowers])

  const loadDetail = useCallback(async (borrower) => {
    setLoadingDetail(true)
    setError('')
    setSuccess('')
    try {
      const response = await api.get('/loans/clearance/detail', {
        params: {
          student_id: borrower.student_id || undefined,
          borrower_email: borrower.email || undefined,
          borrower_name: borrower.name || undefined,
        },
      })
      setDetail(response.data)
    } catch (requestError) {
      setDetail(null)
      setError(requestError.response?.data?.message || 'Data peminjaman peminjam gagal dimuat.')
    } finally {
      setLoadingDetail(false)
    }
  }, [])

  useEffect(() => {
    if (!isDetailPage) return

    const borrower = {
      student_id: searchParams.get('student_id') || '',
      email: searchParams.get('borrower_email') || '',
      name: searchParams.get('borrower_name') || '',
    }

    setSelected(borrower)
    setLetter(emptyLetter())
    loadDetail(borrower)
  }, [isDetailPage, loadDetail, searchParams])

  useEffect(() => {
    if (isDetailPage) return

    setSelected(null)
    setDetail(null)
    setLoadingDetail(false)
  }, [isDetailPage])

  const handleSearch = (event) => {
    event.preventDefault()
    const next = searchInput.trim()
    setSelected(null)
    setDetail(null)
    setSuccess('')
    loadBorrowers(next)
  }

  const handleReset = () => {
    setSearchInput('')
    setSelected(null)
    setDetail(null)
    setError('')
    setSuccess('')
    loadBorrowers()
  }

  const handleSelect = (borrower) => {
    const params = new URLSearchParams()
    if (borrower.student_id) params.set('student_id', borrower.student_id)
    if (borrower.email) params.set('borrower_email', borrower.email)
    if (borrower.name) params.set('borrower_name', borrower.name)
    navigate(`/clearance/detail?${params.toString()}`)
  }

  const updateLetter = (event) => setLetter({ ...letter, [event.target.name]: event.target.value })

  // Pilih teknisi sebagai penandatangan: nama & NIP terisi otomatis, tetap bisa diubah.
  const applyTechnician = (event) => {
    const technician = technicians.find((item) => String(item.id) === event.target.value)
    if (!technician) return
    setLetter((current) => ({
      ...current,
      signatory_name: technician.name || '',
      signatory_nip: technician.nip || '',
    }))
  }

  const handleDownload = async (event) => {
    event.preventDefault()
    if (!selected || !detail) return
    setSubmitting(true)
    setError('')
    setSuccess('')
    try {
      const response = await api.post('/loans/clearance/download', {
        student_id: selected.student_id || '',
        borrower_email: selected.email || '',
        borrower_name: selected.name || '',
        ...letter,
      }, { responseType: 'blob' })
      const safeName = (detail.borrower?.name || 'peminjam').replace(/[^A-Za-z0-9]+/g, '-')
      const result = await downloadBlob(response.data, `surat-bebas-labor-${safeName}.pdf`)
      if (result && !result.ok && !result.canceled) throw new Error(result.message || 'File gagal disimpan.')
      setSuccess('Surat berhasil dibuat dan diunduh.')
    } catch (requestError) {
      if (requestError.response?.data instanceof Blob) {
        try {
          const parsed = JSON.parse(await requestError.response.data.text())
          setError(parsed.message || 'Surat bebas labor tidak dapat diterbitkan.')
          if (parsed.outstanding) {
            setDetail((current) => (current ? { ...current, eligible: false, outstanding: parsed.outstanding } : current))
          }
        } catch {
          setError('Surat bebas labor tidak dapat diterbitkan.')
        }
      } else {
        setError(requestError.response?.data?.message || requestError.message || 'Surat bebas labor gagal dibuat.')
      }
    } finally {
      setSubmitting(false)
    }
  }

  const totals = detail?.totals || {}
  const outstanding = detail?.outstanding || []
  const history = detail?.history || []

  return (
    <div className="mx-auto max-w-5xl">
      <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">{isDetailPage ? 'Detail Bebas Labor' : 'Bebas Labor'}</h1>
          <p className="mt-1 text-slate-500">
            {isDetailPage
              ? 'Periksa riwayat peminjaman dan buat surat berdasarkan data peminjam.'
              : 'Surat keterangan bebas peminjaman laboratorium, diambil dari data peminjaman barang.'}
          </p>
        </div>
        {isDetailPage ? (
          <button
            type="button"
            onClick={() => navigate('/clearance')}
            className="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50"
          >
            <ArrowLeft className="h-4 w-4" />
            Kembali ke daftar
          </button>
        ) : (
          <button
            type="button"
            onClick={handleReset}
            className="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50"
          >
            <RefreshCw className="h-4 w-4" />
            Muat ulang
          </button>
        )}
      </div>

      {error && <div className="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>}
      {success && <div className="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{success}</div>}

      {!isDetailPage && <form onSubmit={handleSearch} className="mb-4 flex flex-col gap-2 sm:flex-row">
        <div className="relative flex-1">
          <Search className="absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
          <input
            type="text"
            value={searchInput}
            onChange={(event) => setSearchInput(event.target.value)}
            placeholder="Cari nama, NIM, atau email peminjam..."
            className="w-full rounded-lg border border-slate-300 py-2.5 pl-10 pr-4 outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500"
          />
        </div>
        <button
          type="submit"
          disabled={searching}
          className="inline-flex items-center justify-center gap-2 rounded-lg bg-cyan-600 px-5 py-2.5 font-medium text-white hover:bg-cyan-700 disabled:opacity-50"
        >
          <UserRoundSearch className="h-5 w-5" />
          {searching ? 'Mencari...' : 'Cari Peminjam'}
        </button>
      </form>}

      {!isDetailPage && <section className="rounded-xl border border-slate-200 bg-white">
        <h2 className="border-b border-slate-100 px-5 py-4 font-semibold text-slate-900">
          Pilih peminjam {borrowers.length > 0 && <span className="text-sm font-normal text-slate-500">({borrowers.length} hasil)</span>}
        </h2>
        {searching ? (
          <p className="px-5 py-8 text-center text-sm text-slate-500">Memuat daftar peminjam...</p>
        ) : borrowers.length === 0 ? (
          <p className="px-5 py-8 text-center text-sm text-slate-500">Tidak ada peminjam yang cocok dengan pencarian.</p>
        ) : (
          <ul className="divide-y divide-slate-100">
            {borrowers.map((borrower) => {
              const isActive = selected?.student_id === borrower.student_id
                && selected?.email === borrower.email
                && selected?.name === borrower.name
              return (
                <li key={`${borrower.student_id || borrower.email}-${borrower.name}`}>
                  <button
                    type="button"
                    onClick={() => handleSelect(borrower)}
                    className={`flex w-full flex-col gap-2 px-5 py-4 text-left transition-colors sm:flex-row sm:items-center sm:justify-between ${isActive ? 'bg-cyan-50' : 'hover:bg-slate-50'}`}
                  >
                    <div>
                      <p className="font-medium text-slate-900">{borrower.name}</p>
                      <p className="text-xs text-slate-500">
                        {borrower.student_id ? `NIM ${borrower.student_id} · ` : ''}{borrower.email}
                      </p>
                      <p className="mt-1 text-xs text-slate-400">
                        {borrower.total_loans} transaksi · {borrower.total_qty} unit · terakhir {formatDate(borrower.last_loan_at)}
                      </p>
                    </div>
                    <span className={`inline-flex items-center gap-1.5 self-start rounded-full px-3 py-1 text-xs font-medium ${borrower.eligible ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'}`}>
                      {borrower.eligible ? <BadgeCheck className="h-3.5 w-3.5" /> : <ShieldAlert className="h-3.5 w-3.5" />}
                      {borrower.eligible ? 'Bebas Labor' : `${borrower.outstanding_loans} belum kembali`}
                    </span>
                  </button>
                </li>
              )
            })}
          </ul>
        )}
      </section>}

      {isDetailPage && loadingDetail && <p className="mt-6 rounded-xl border border-slate-200 bg-white px-5 py-8 text-center text-sm text-slate-500">Memuat data peminjaman peminjam...</p>}

      {isDetailPage && !loadingDetail && detail && selected && (
        <div className="mt-6 space-y-6">
          {/* DETAIL_SECTIONS */}
          <section className="rounded-xl border border-slate-200 bg-white p-6">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
              <div>
                <p className="text-xs uppercase tracking-wide text-slate-400">Peminjam</p>
                <h2 className="text-xl font-bold text-slate-900">{detail.borrower.name}</h2>
                <p className="mt-1 text-sm text-slate-500">
                  {detail.borrower.student_id ? `NIM / NIP ${detail.borrower.student_id} · ` : ''}{detail.borrower.email}
                </p>
              </div>
              <span className={`inline-flex items-center gap-1.5 self-start rounded-full px-3 py-1 text-sm font-medium ${detail.eligible ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'}`}>
                {detail.eligible ? <BadgeCheck className="h-4 w-4" /> : <ShieldAlert className="h-4 w-4" />}
                {detail.eligible ? 'Bebas Labor' : 'Belum Bebas Labor'}
              </span>
            </div>
            <div className="mt-5 grid grid-cols-2 gap-4 md:grid-cols-4">
              {[
                ['Transaksi', totals?.total_loans ?? 0],
                ['Total unit barang', totals?.total_qty ?? 0],
                ['Belum kembali', totals?.outstanding_loans ?? 0],
                ['Sudah dikembalikan', totals?.returned_loans ?? 0],
              ].map(([label, value]) => (
                <div key={label} className="rounded-lg border border-slate-200 p-3">
                  <p className="text-xs text-slate-500">{label}</p>
                  <p className="mt-1 text-xl font-bold text-slate-900">{value}</p>
                </div>
              ))}
            </div>
          </section>

          {!detail.eligible && (
            <section className="rounded-xl border border-red-200 bg-red-50 p-6">
              <h2 className="flex items-center gap-2 font-semibold text-red-700">
                <AlertTriangle className="h-5 w-5" />
                Peminjam masih memiliki tanggungan
              </h2>
              <p className="mt-1 text-sm text-red-600">
                Masih ada {outstanding.length} transaksi ({totals?.outstanding_qty ?? 0} unit barang) yang belum
                dikembalikan. Surat yang diunduh akan otomatis berisi <span className="font-medium">Surat Keterangan
                Tanggungan</span> (daftar barang yang belum dikembalikan), bukan surat bebas labor. Proses
                pengembalian dapat dilakukan melalui menu <span className="font-medium">Scan Pengembalian</span>.
              </p>
              <div className="mt-4 overflow-x-auto rounded-lg border border-red-200 bg-white">
                <table className="w-full text-left text-sm">
                  <thead className="bg-slate-50">
                    <tr>
                      <th className="px-4 py-3 font-medium text-slate-500">Kode transaksi</th>
                      <th className="px-4 py-3 font-medium text-slate-500">Barang</th>
                      <th className="px-4 py-3 font-medium text-slate-500">Jumlah</th>
                      <th className="px-4 py-3 font-medium text-slate-500">Tanggal pinjam</th>
                      <th className="px-4 py-3 font-medium text-slate-500">Status</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {outstanding.map((loan) => (
                      <tr key={loan.id}>
                        <td className="px-4 py-3 font-mono text-xs text-slate-600">{loan.loan_code}</td>
                        <td className="px-4 py-3 text-slate-700">{loan.item_summary}</td>
                        <td className="px-4 py-3 text-slate-700">{loan.qty} unit</td>
                        <td className="px-4 py-3 text-slate-500">{formatDate(loan.borrowed_at || loan.created_at)}</td>
                        <td className="px-4 py-3 text-slate-600">{loan.status_label}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </section>
          )}

          <form onSubmit={handleDownload} className="rounded-xl border border-slate-200 bg-white p-6">
              <h2 className="flex items-center gap-2 font-semibold text-slate-900">
                <FileCheck2 className="h-5 w-5 text-cyan-600" />
                {detail.eligible ? 'Data surat bebas labor' : 'Data surat keterangan tanggungan'}
              </h2>
              <p className="mt-1 text-sm text-slate-500">
                {detail.eligible
                  ? 'Isi surat (kop surat, identitas peminjam, daftar barang yang sudah dikembalikan, dan pernyataan bebas tanggungan) dibuat otomatis dari data peminjaman.'
                  : 'Isi surat (kop surat, identitas peminjam, dan daftar barang yang belum dikembalikan) dibuat otomatis dari data peminjaman.'}
              </p>
              <div className="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                <label className="text-sm font-medium text-slate-700">
                  Keperluan surat <span className="text-red-600" aria-label="wajib diisi">*</span>
                  <input
                    required
                    name="purpose"
                    list="clearance-purpose-list"
                    value={letter.purpose}
                    onChange={updateLetter}
                    placeholder="Contoh: Persyaratan bebas pustaka"
                    className="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500"
                  />
                  <datalist id="clearance-purpose-list">
                    {purposeOptions.map((option) => <option key={option} value={option} />)}
                  </datalist>
                </label>
                {/* FORM_FIELDS */}
                <label className="text-sm font-medium text-slate-700">
                  Tanggal surat
                  <input
                    type="date"
                    name="letter_date"
                    value={letter.letter_date}
                    onChange={updateLetter}
                    className="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500"
                  />
                </label>
                <label className="text-sm font-medium text-slate-700">
                  Laboratorium <span className="text-xs font-normal text-slate-400">(opsional)</span>
                  <input
                    name="laboratory"
                    value={letter.laboratory}
                    onChange={updateLetter}
                    placeholder="Contoh: Laboratorium Komputer"
                    className="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500"
                  />
                </label>
                {technicians.length > 0 && (
                  <label className="text-sm font-medium text-slate-700 md:col-span-2">
                    Pilih teknisi penandatangan <span className="text-xs font-normal text-slate-400">(mengisi nama &amp; NIP otomatis)</span>
                    <select
                      onChange={applyTechnician}
                      defaultValue=""
                      className="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500"
                    >
                      <option value="">Pilih teknisi</option>
                      {technicians.map((technician) => (
                        <option key={technician.id} value={technician.id}>{technician.name} - NIP. {technician.nip}</option>
                      ))}
                    </select>
                  </label>
                )}
                <label className="text-sm font-medium text-slate-700">
                  Nama penandatangan <span className="text-red-600" aria-label="wajib diisi">*</span>
                  <input
                    required
                    name="signatory_name"
                    value={letter.signatory_name}
                    onChange={updateLetter}
                    placeholder="Nama petugas laboratorium"
                    className="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500"
                  />
                </label>
                <label className="text-sm font-medium text-slate-700">
                  NIP penandatangan <span className="text-red-600" aria-label="wajib diisi">*</span>
                  <input
                    required
                    name="signatory_nip"
                    value={letter.signatory_nip}
                    onChange={updateLetter}
                    placeholder="NIP petugas laboratorium"
                    className="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500"
                  />
                </label>
              </div>
              <button
                type="submit"
                disabled={submitting}
                className="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-cyan-600 px-5 py-3 font-semibold text-white transition-colors hover:bg-cyan-700 disabled:cursor-not-allowed disabled:opacity-50"
              >
                <Download className="h-5 w-5" />
                {submitting ? 'Membuat surat...' : (detail.eligible ? 'Buat & Unduh Surat Bebas Labor' : 'Buat & Unduh Surat Tanggungan')}
              </button>
          </form>

          {history.length > 0 && (
            <section className="rounded-xl border border-slate-200 bg-white">
              <h2 className="flex items-center gap-2 border-b border-slate-100 px-5 py-4 font-semibold text-slate-900">
                <History className="h-5 w-5 text-slate-500" />
                Barang yang sudah dikembalikan
              </h2>
              <div className="overflow-x-auto">
                <table className="w-full text-left text-sm">
                  <thead className="bg-slate-50">
                    <tr>
                      <th className="px-5 py-3 font-medium text-slate-500">Kode transaksi</th>
                      <th className="px-5 py-3 font-medium text-slate-500">Barang</th>
                      <th className="px-5 py-3 font-medium text-slate-500">Jumlah</th>
                      <th className="px-5 py-3 font-medium text-slate-500">Tanggal kembali</th>
                      <th className="px-5 py-3 font-medium text-slate-500">Kondisi</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {history.map((loan) => (
                      <tr key={loan.id}>
                        <td className="px-5 py-3 font-mono text-xs text-slate-600">{loan.loan_code}</td>
                        <td className="px-5 py-3 text-slate-700">{loan.item_summary}</td>
                        <td className="px-5 py-3 text-slate-700">{loan.qty} unit</td>
                        <td className="px-5 py-3 text-slate-500">{formatDate(loan.returned_at)}</td>
                        <td className="px-5 py-3 text-slate-600">{loan.condition_on_return || '-'}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </section>
          )}
        </div>
      )}
    </div>
  )
}

export default Clearance
