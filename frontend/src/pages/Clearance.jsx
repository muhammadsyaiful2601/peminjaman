import { useCallback, useEffect, useState } from 'react'
import { useLocation, useNavigate, useSearchParams } from 'react-router-dom'
import api from '../api/axios'
import { downloadBlob } from '../utils/downloadBlob'
import { ClearanceLetterModal } from '../components/ClearanceLetterModal'
import TablePagination from '../components/TablePagination'
import useTablePagination, { ROWS_PER_PAGE } from '../hooks/useTablePagination'
import {
  AlertTriangle,
  ArrowLeft,
  BadgeCheck,
  CheckSquare,
  Download,
  FileCheck2,
  History,
  Info,
  Printer,
  RefreshCw,
  Search,
  ShieldAlert,
  Square,
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

// Jumlah baris per halaman pada dua tabel di halaman detail peminjam
// (barang belum dikembalikan & barang yang sudah dikembalikan).
const PER_PAGE = ROWS_PER_PAGE

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

async function printHtmlDocument(html) {
  const iframe = document.createElement('iframe')
  iframe.setAttribute('aria-hidden', 'true')
  iframe.style.position = 'fixed'
  iframe.style.left = '-10000px'
  iframe.style.top = '0'
  iframe.style.width = '210mm'
  iframe.style.height = '297mm'
  iframe.style.border = '0'
  iframe.style.visibility = 'hidden'
  document.body.appendChild(iframe)

  const doc = iframe.contentWindow.document
  doc.open()
  doc.write(html)
  doc.close()

  await new Promise((resolve) => {
    let resolved = false
    const done = () => {
      if (resolved) return
      resolved = true
      resolve()
    }
    const images = Array.from(doc.images || [])
    if (images.length === 0 || images.every((img) => img.complete)) {
      setTimeout(done, 150)
      return
    }
    let pending = images.length
    images.forEach((img) => {
      if (img.complete) {
        pending -= 1
        if (pending === 0) done()
      } else {
        img.addEventListener('load', () => {
          pending -= 1
          if (pending === 0) done()
        })
        img.addEventListener('error', () => {
          pending -= 1
          if (pending === 0) done()
        })
      }
    })
    setTimeout(done, 2000)
  })

  try {
    iframe.contentWindow.focus()
    iframe.contentWindow.print()
  } finally {
    setTimeout(() => {
      iframe.remove()
    }, 60000)
  }
}

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

  // Multi-select untuk Cetak Masal (khusus bebas_labor)
  const [selectedKeys, setSelectedKeys] = useState(new Set())

  // Modal penerbitan surat: 'download' | 'print' | 'bulk-print' | null
  const [modalAction, setModalAction] = useState(null)
  const [targetBorrower, setTargetBorrower] = useState(null)
  const [modalSubmitting, setModalSubmitting] = useState(false)
  const [modalError, setModalError] = useState('')

  const borrowerKey = (b) => `${b.student_id || b.email || ''}:::${b.name || ''}`

  const loadBorrowers = useCallback(async (search = '') => {
    setSearching(true)
    setError('')
    try {
      const response = await api.get('/loans/clearance/borrowers', { params: search ? { search } : {} })
      const list = response.data.data || []
      setBorrowers(list)
      setSelectedKeys((prev) => {
        const next = new Set()
        const eligibleKeys = new Set(list.filter((b) => Boolean(b.eligible)).map(borrowerKey))
        prev.forEach((key) => {
          if (eligibleKeys.has(key)) next.add(key)
        })
        return next
      })
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
    const studentId = searchParams.get('student_id') || ''
    const email = searchParams.get('email') || ''
    const name = searchParams.get('name') || ''
    if (!studentId && !email && !name) {
      navigate('/clearance', { replace: true })
      return
    }
    const current = { student_id: studentId, email, name }
    setSelected(current)
    loadDetail(current)
  }, [isDetailPage, searchParams, navigate, loadDetail])

  const handleSearch = (event) => {
    event.preventDefault()
    loadBorrowers(searchInput)
  }

  const handleReset = () => {
    setSearchInput('')
    setSelectedKeys(new Set())
    loadBorrowers('')
  }

  const handleSelect = (borrower) => {
    setSelected(borrower)
    setLetter(emptyLetter())
    const params = new URLSearchParams()
    if (borrower.student_id) params.set('student_id', borrower.student_id)
    if (borrower.email) params.set('email', borrower.email)
    if (borrower.name) params.set('name', borrower.name)
    navigate(`/clearance/detail?${params.toString()}`)
  }

  const updateLetter = (event) => {
    const { name, value } = event.target
    setLetter((current) => ({ ...current, [name]: value }))
  }

  const applyTechnician = (event) => {
    const technician = technicians.find((item) => String(item.id) === event.target.value)
    if (!technician) return
    setLetter((current) => ({
      ...current,
      signatory_name: technician.name,
      signatory_nip: technician.nip,
    }))
  }

  // --- Multi-select handlers (bebas labor, termasuk mahasiswa tanpa riwayat) ---
  const eligibleBorrowers = borrowers.filter((b) => Boolean(b.eligible))
  const allEligibleSelected = eligibleBorrowers.length > 0
    && eligibleBorrowers.every((b) => selectedKeys.has(borrowerKey(b)))

  const toggleSelectAll = () => {
    if (allEligibleSelected) {
      setSelectedKeys(new Set())
    } else {
      const next = new Set()
      eligibleBorrowers.forEach((b) => next.add(borrowerKey(b)))
      setSelectedKeys(next)
    }
  }

  const toggleSelectBorrower = (borrower, event) => {
    event.stopPropagation()
    const key = borrowerKey(borrower)
    setSelectedKeys((prev) => {
      const next = new Set(prev)
      if (next.has(key)) next.delete(key)
      else next.add(key)
      return next
    })
  }

  // --- Modal action triggers ---
  const openRowDownload = (borrower, event) => {
    event.stopPropagation()
    setTargetBorrower(borrower)
    setModalAction('download')
    setModalError('')
  }

  const openRowPrint = (borrower, event) => {
    event.stopPropagation()
    setTargetBorrower(borrower)
    setModalAction('print')
    setModalError('')
  }

  const openBulkPrint = () => {
    if (selectedKeys.size === 0) return
    setTargetBorrower(null)
    setModalAction('bulk-print')
    setModalError('')
  }

  const closeModal = () => {
    setModalAction(null)
    setTargetBorrower(null)
    setModalError('')
  }

  // --- Submit handler modal (download / print / bulk-print) ---
  const handleModalSubmit = async (event) => {
    event.preventDefault()
    setModalError('')
    setModalSubmitting(true)

    try {
      if (modalAction === 'download') {
        const borrower = targetBorrower || selected
        const response = await api.post('/loans/clearance/download', {
          student_id: borrower?.student_id || '',
          borrower_email: borrower?.email || '',
          borrower_name: borrower?.name || '',
          purpose: letter.purpose,
          letter_date: letter.letter_date || undefined,
          laboratory: letter.laboratory || undefined,
          signatory_name: letter.signatory_name,
          signatory_nip: letter.signatory_nip,
        }, { responseType: 'blob' })

        const disposition = response.headers['content-disposition'] || ''
        const match = disposition.match(/filename="?([^";]+)"?/)
        const filename = match ? match[1] : 'surat-bebas-labor.pdf'
        await downloadBlob(response.data, filename)
        setSuccess(`Surat bebas labor ${borrower?.name || ''} berhasil diunduh.`)
        closeModal()
      } else if (modalAction === 'print') {
        const borrower = targetBorrower || selected
        const response = await api.post('/loans/clearance/print', {
          borrowers: [{
            student_id: borrower?.student_id || '',
            borrower_email: borrower?.email || '',
            borrower_name: borrower?.name || '',
          }],
          purpose: letter.purpose,
          letter_date: letter.letter_date || undefined,
          laboratory: letter.laboratory || undefined,
          signatory_name: letter.signatory_name,
          signatory_nip: letter.signatory_nip,
        }, { responseType: 'text' })

        await printHtmlDocument(response.data)
        setSuccess(`Dialog cetak surat untuk ${borrower?.name || ''} telah dibuka.`)
        closeModal()
      } else if (modalAction === 'bulk-print') {
        const selectedBorrowers = eligibleBorrowers
          .filter((b) => selectedKeys.has(borrowerKey(b)))
          .map((b) => ({
            student_id: b.student_id || '',
            borrower_email: b.email || '',
            borrower_name: b.name || '',
          }))

        if (selectedBorrowers.length === 0) {
          throw new Error('Tidak ada mahasiswa bebas labor yang dipilih.')
        }

        const response = await api.post('/loans/clearance/print', {
          borrowers: selectedBorrowers,
          purpose: letter.purpose,
          letter_date: letter.letter_date || undefined,
          laboratory: letter.laboratory || undefined,
          signatory_name: letter.signatory_name,
          signatory_nip: letter.signatory_nip,
        }, { responseType: 'text' })

        await printHtmlDocument(response.data)
        setSuccess(`Dialog cetak masal untuk ${selectedBorrowers.length} mahasiswa telah dibuka.`)
        closeModal()
      }
    } catch (requestError) {
      let message = requestError.message || 'Gagal memproses surat.'
      if (requestError.response?.data) {
        if (typeof requestError.response.data === 'string') {
          try {
            const parsed = JSON.parse(requestError.response.data)
            message = parsed.message || Object.values(parsed.errors || {})[0]?.[0] || message
          } catch {
            // text biasa
          }
        } else {
          message = requestError.response.data.message
            || Object.values(requestError.response.data.errors || {})[0]?.[0]
            || message
        }
      }
      setModalError(message)
    } finally {
      setModalSubmitting(false)
    }
  }

  // Unduh dari form detail page
  const handleDownload = async (event) => {
    event.preventDefault()
    if (!selected) return

    setSubmitting(true)
    setError('')
    setSuccess('')

    try {
      const response = await api.post('/loans/clearance/download', {
        student_id: selected.student_id || '',
        borrower_email: selected.email || '',
        borrower_name: selected.name || '',
        purpose: letter.purpose,
        letter_date: letter.letter_date || undefined,
        laboratory: letter.laboratory || undefined,
        signatory_name: letter.signatory_name,
        signatory_nip: letter.signatory_nip,
      }, {
        responseType: 'blob',
      })

      const isEligible = response.headers['x-clearance-eligible'] === '1'
      const filename = isEligible
        ? `surat-bebas-labor-${selected.student_id || selected.name}.pdf`
        : `surat-tanggungan-labor-${selected.student_id || selected.name}.pdf`

      await downloadBlob(response.data, filename)
      setSuccess(isEligible
        ? 'Surat bebas labor berhasil diunduh.'
        : 'Surat keterangan tanggungan peminjaman berhasil diunduh.')
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
  const hasLoans = detail?.has_loans ?? false
  // Masing-masing tabel detail dipaginasi 10 baris per halaman dengan nomor
  // urut yang berlanjut antar halaman.
  const outstandingTable = useTablePagination(outstanding, { perPage: PER_PAGE, resetKey: detail })
  const historyTable = useTablePagination(history, { perPage: PER_PAGE, resetKey: detail })
  // Peminjam tanpa tanggungan — termasuk mahasiswa yang belum pernah meminjam —
  // tetap berstatus bebas labor dan suratnya dapat diterbitkan.
  const eligible = detail?.eligible ?? false
  const letterStatusLabel = !eligible
    ? 'Belum Bebas Labor'
    : (hasLoans ? 'Bebas Labor' : 'Bebas Labor · Tanpa Riwayat')
  const letterStatusClass = eligible ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'
  const LetterStatusIcon = eligible ? BadgeCheck : ShieldAlert

  return (
    <div className="mx-auto max-w-5xl">
      <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">{isDetailPage ? 'Detail Bebas Labor' : 'Bebas Labor'}</h1>
          <p className="mt-1 text-slate-500">
            {isDetailPage
              ? 'Periksa riwayat peminjaman dan kelayakan surat peminjam.'
              : 'Daftar mahasiswa (sumber data sama dengan menu Data Mahasiswa) beserta status bebas peminjaman laboratorium.'}
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

      {!isDetailPage && (
        <>
          <form onSubmit={handleSearch} className="mb-4 flex flex-col gap-2 sm:flex-row">
            <div className="relative flex-1">
              <Search className="absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
              <input
                type="text"
                value={searchInput}
                onChange={(event) => setSearchInput(event.target.value)}
                placeholder="Cari nama, NIM, atau email mahasiswa..."
                className="w-full rounded-lg border border-slate-300 py-2.5 pl-10 pr-4 outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500"
              />
            </div>
            <button
              type="submit"
              disabled={searching}
              className="inline-flex items-center justify-center gap-2 rounded-lg bg-cyan-600 px-5 py-2.5 font-medium text-white hover:bg-cyan-700 disabled:opacity-50"
            >
              <UserRoundSearch className="h-5 w-5" />
              {searching ? 'Memuat...' : 'Cari Mahasiswa'}
            </button>
          </form>

          {/* Toolbar Cetak Masal (khusus bebas labor) */}
          {eligibleBorrowers.length > 0 && (
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-cyan-100 bg-cyan-50/60 px-4 py-3 text-sm">
              <div className="flex items-center gap-2">
                <button
                  type="button"
                  onClick={toggleSelectAll}
                  className="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-1.5 font-medium text-slate-700 shadow-sm hover:bg-slate-50"
                >
                  {allEligibleSelected ? <CheckSquare className="h-4 w-4 text-cyan-600" /> : <Square className="h-4 w-4 text-slate-400" />}
                  {allEligibleSelected ? 'Batalkan Semua' : 'Pilih Semua Bebas Labor'}
                </button>
                <span className="text-slate-600">
                  <strong>{selectedKeys.size}</strong> dari {eligibleBorrowers.length} mahasiswa bebas labor dipilih
                </span>
              </div>
              <button
                type="button"
                disabled={selectedKeys.size === 0}
                onClick={openBulkPrint}
                className="inline-flex items-center gap-2 rounded-lg bg-cyan-600 px-4 py-2 font-medium text-white shadow-sm hover:bg-cyan-700 disabled:cursor-not-allowed disabled:opacity-50"
              >
                <Printer className="h-4 w-4" />
                Cetak Masal {selectedKeys.size > 0 && `(${selectedKeys.size})`}
              </button>
            </div>
          )}

          <section className="rounded-xl border border-slate-200 bg-white">
            <h2 className="border-b border-slate-100 px-5 py-4 font-semibold text-slate-900">
              Pilih mahasiswa {borrowers.length > 0 && <span className="text-sm font-normal text-slate-500">({borrowers.length} hasil)</span>}
            </h2>
            {searching ? (
              <p className="px-5 py-8 text-center text-sm text-slate-500">Memuat daftar mahasiswa...</p>
            ) : borrowers.length === 0 ? (
              <p className="px-5 py-8 text-center text-sm text-slate-500">Tidak ada mahasiswa yang cocok dengan pencarian.</p>
            ) : (
              <ul className="divide-y divide-slate-100">
                {borrowers.map((borrower) => {
                  // Bebas labor (termasuk yang belum pernah meminjam) boleh
                  // dipilih & dicetak; yang masih bertanggungan tidak.
                  const isEligible = Boolean(borrower.eligible)
                  const key = borrowerKey(borrower)
                  const isChecked = selectedKeys.has(key)

                  return (
                    <li
                      key={key}
                      onClick={() => handleSelect(borrower)}
                      className="group flex flex-col gap-3 px-5 py-4 transition-colors hover:bg-slate-50 sm:flex-row sm:items-center sm:justify-between cursor-pointer"
                    >
                      <div className="flex items-start gap-3">
                        {/* Checkbox multi-select untuk peminjam bebas labor (termasuk tanpa riwayat) */}
                        <div className="pt-0.5">
                          {isEligible ? (
                            <button
                              type="button"
                              onClick={(e) => toggleSelectBorrower(borrower, e)}
                              aria-label={`Pilih ${borrower.name}`}
                              className="rounded p-1 text-slate-500 hover:bg-slate-200"
                            >
                              {isChecked ? (
                                <CheckSquare className="h-5 w-5 text-cyan-600" />
                              ) : (
                                <Square className="h-5 w-5 text-slate-400" />
                              )}
                            </button>
                          ) : (
                            <span
                              title="Hanya mahasiswa berstatus Bebas Labor yang dapat dipilih masal"
                              className="inline-block h-5 w-5 opacity-20"
                            >
                              <Square className="h-5 w-5 text-slate-300" />
                            </span>
                          )}
                        </div>

                        <div>
                          <p className="font-medium text-slate-900">{borrower.name}</p>
                          <p className="text-xs text-slate-500">
                            {borrower.student_id ? `NIM ${borrower.student_id} · ` : ''}{borrower.email}{borrower.phone ? ` · ${borrower.phone}` : ''}
                          </p>
                          <p className="mt-1 text-xs text-slate-400">
                            {borrower.has_loans
                              ? `${borrower.total_loans} transaksi · ${borrower.total_qty} unit · terakhir ${formatDate(borrower.last_loan_at)}`
                              : 'Belum ada riwayat peminjaman · surat bebas labor tetap dapat diterbitkan'}
                          </p>
                          {borrower.has_student_record === false && (
                            <p className="mt-1 text-xs font-medium text-amber-600">
                              Peminjam manual — belum terdaftar pada data mahasiswa
                            </p>
                          )}
                        </div>
                      </div>

                      {/* Status badge & tombol aksi Unduh / Print di bagian kanan */}
                      <div className="flex flex-wrap items-center gap-2 self-start sm:self-center">
                        {borrower.eligible ? (
                          <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1 text-xs font-medium text-emerald-700">
                            <BadgeCheck className="h-3.5 w-3.5" />
                            {borrower.has_loans ? 'Bebas Labor' : 'Bebas Labor · Tanpa Riwayat'}
                          </span>
                        ) : (
                          <span className="inline-flex items-center gap-1.5 rounded-full bg-red-100 px-3 py-1 text-xs font-medium text-red-700">
                            <ShieldAlert className="h-3.5 w-3.5" />
                            {borrower.outstanding_loans + ' belum kembali'}
                          </span>
                        )}

                        {/* Tombol Unduh & Cetak tersedia untuk peminjam bebas labor */}
                        {isEligible && (
                          <div className="flex items-center gap-1.5 pl-2 sm:border-l sm:border-slate-200">
                            <button
                              type="button"
                              onClick={(e) => openRowDownload(borrower, e)}
                              title="Unduh Surat Bebas Labor (PDF)"
                              className="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 shadow-sm hover:border-cyan-300 hover:bg-cyan-50 hover:text-cyan-700"
                            >
                              <Download className="h-3.5 w-3.5 text-cyan-600" />
                              Unduh
                            </button>
                            <button
                              type="button"
                              onClick={(e) => openRowPrint(borrower, e)}
                              title="Cetak Surat Bebas Labor"
                              className="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 shadow-sm hover:border-cyan-300 hover:bg-cyan-50 hover:text-cyan-700"
                            >
                              <Printer className="h-3.5 w-3.5 text-cyan-600" />
                              Cetak
                            </button>
                          </div>
                        )}
                      </div>
                    </li>
                  )
                })}
              </ul>
            )}
          </section>
        </>
      )}

      {isDetailPage && loadingDetail && <p className="mt-6 rounded-xl border border-slate-200 bg-white px-5 py-8 text-center text-sm text-slate-500">Memuat data peminjaman peminjam...</p>}

      {isDetailPage && !loadingDetail && detail && selected && (
        <div className="mt-6 space-y-6">
          <section className="rounded-xl border border-slate-200 bg-white p-6">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
              <div>
                <p className="text-xs uppercase tracking-wide text-slate-400">Peminjam</p>
                <h2 className="text-xl font-bold text-slate-900">{detail.borrower.name}</h2>
                <p className="mt-1 text-sm text-slate-500">
                  {detail.borrower.student_id ? `NIM / NIP ${detail.borrower.student_id} · ` : ''}{detail.borrower.email}
                </p>
              </div>
              <span className={`inline-flex items-center gap-1.5 self-start rounded-full px-3 py-1 text-sm font-medium ${letterStatusClass}`}>
                <LetterStatusIcon className="h-4 w-4" />
                {letterStatusLabel}
              </span>
            </div>
            <div className="mt-6 grid grid-cols-2 gap-4 border-t border-slate-100 pt-6 sm:grid-cols-4">
              <div>
                <p className="text-xs text-slate-500">Total Transaksi</p>
                <p className="mt-1 text-lg font-semibold text-slate-900">{totals.total_loans ?? 0}</p>
              </div>
              <div>
                <p className="text-xs text-slate-500">Total Unit Barang</p>
                <p className="mt-1 text-lg font-semibold text-slate-900">{totals.total_qty ?? 0}</p>
              </div>
              <div>
                <p className="text-xs text-slate-500">Belum Kembali</p>
                <p className={`mt-1 text-lg font-semibold ${(totals.outstanding_loans ?? 0) > 0 ? 'text-red-600' : 'text-slate-900'}`}>
                  {totals.outstanding_loans ?? 0} transaksi ({totals.outstanding_qty ?? 0} unit)
                </p>
              </div>
              <div>
                <p className="text-xs text-slate-500">Sudah Selesai</p>
                <p className="mt-1 text-lg font-semibold text-emerald-600">
                  {totals.returned_loans ?? 0} transaksi ({totals.returned_qty ?? 0} unit)
                </p>
              </div>
            </div>
          </section>

          {!hasLoans && (
            <div className="rounded-xl border border-cyan-200 bg-cyan-50 p-6 text-sm text-cyan-900">
              <div className="flex items-start gap-3">
                <Info className="h-5 w-5 shrink-0 text-cyan-600" />
                <div>
                  <h3 className="font-semibold text-cyan-900">Belum ada riwayat peminjaman</h3>
                  <p className="mt-1">
                    Mahasiswa ini belum pernah meminjam barang di laboratorium (atau seluruh transaksinya ditolak). Surat bebas labor <strong>tetap dapat diterbitkan</strong>
                    {' '}dengan keterangan bahwa tidak ada transaksi peminjaman yang tercatat atas namanya.
                  </p>
                </div>
              </div>
            </div>
          )}

          {outstanding.length > 0 && (
            <section className="rounded-xl border border-red-200 bg-red-50/50 p-6">
              <h2 className="flex items-center gap-2 font-semibold text-red-900">
                <AlertTriangle className="h-5 w-5 text-red-600" />
                Barang yang belum dikembalikan ({outstanding.length} transaksi)
              </h2>
              <p className="mt-1 text-sm text-red-700">
                Peminjam masih memiliki tanggungan peminjaman. Surat yang diunduh otomatis berupa <strong>Surat Keterangan Tanggungan</strong> yang memuat daftar barang berikut agar segera diselesaikan.
              </p>
              <div className="mt-4 overflow-x-auto rounded-lg border border-red-200 bg-white">
                <table className="w-full text-left text-sm">
                  <thead className="bg-red-50 text-red-900">
                    <tr>
                      <th className="w-14 px-4 py-3 text-center font-medium">No.</th>
                      <th className="px-4 py-3 font-medium">Kode transaksi</th>
                      <th className="px-4 py-3 font-medium">Barang</th>
                      <th className="px-4 py-3 font-medium">Jumlah</th>
                      <th className="px-4 py-3 font-medium">Tanggal pinjam</th>
                      <th className="px-4 py-3 font-medium">Status</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-red-100">
                    {outstandingTable.pageItems.map((loan, index) => (
                      <tr key={loan.id}>
                        <td className="px-4 py-3 text-center text-slate-400">{outstandingTable.rowOffset + index + 1}</td>
                        <td className="px-4 py-3 font-mono text-xs">{loan.loan_code}</td>
                        <td className="px-4 py-3">{loan.item_summary}</td>
                        <td className="px-4 py-3">{loan.qty} unit</td>
                        <td className="px-4 py-3">{formatDate(loan.borrowed_at)}</td>
                        <td className="px-4 py-3">
                          <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">
                            {loan.status === 'borrowed' ? 'Sedang dipinjam' : 'Menunggu verifikasi'}
                          </span>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <div className="mt-3">
                <TablePagination
                  page={outstandingTable.page}
                  lastPage={outstandingTable.lastPage}
                  onPageChange={outstandingTable.goToPage}
                  total={outstandingTable.total}
                  perPage={PER_PAGE}
                />
              </div>
            </section>
          )}

          {hasLoans && (
            <form onSubmit={handleDownload} className="rounded-xl border border-slate-200 bg-white p-6">
              <h2 className="flex items-center gap-2 font-semibold text-slate-900">
                <FileCheck2 className="h-5 w-5 text-cyan-600" />
                {detail.eligible
                  ? (hasLoans ? 'Data surat bebas labor' : 'Data surat bebas labor (tanpa riwayat peminjaman)')
                  : 'Data surat keterangan tanggungan'}
              </h2>
              <p className="mt-1 text-sm text-slate-500">
                {detail.eligible
                  ? (hasLoans
                    ? 'Isi surat (kop surat, identitas peminjam, daftar barang yang sudah dikembalikan, dan pernyataan bebas tanggungan) dibuat otomatis dari data peminjaman.'
                    : 'Isi surat (kop surat, identitas peminjam, keterangan belum ada transaksi peminjaman, dan pernyataan bebas tanggungan) dibuat otomatis dari data mahasiswa.')
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
              <div className="mt-5 flex flex-col gap-3 sm:flex-row">
                <button
                  type="submit"
                  disabled={submitting}
                  className="inline-flex flex-1 items-center justify-center gap-2 rounded-lg bg-cyan-600 px-5 py-3 font-semibold text-white transition-colors hover:bg-cyan-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                  <Download className="h-5 w-5" />
                  {submitting ? 'Membuat surat...' : (detail.eligible ? 'Buat & Unduh Surat Bebas Labor' : 'Buat & Unduh Surat Tanggungan')}
                </button>
                {detail.eligible && (
                  <button
                    type="button"
                    onClick={() => {
                      setTargetBorrower(selected)
                      setModalAction('print')
                      setModalError('')
                    }}
                    className="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-5 py-3 font-semibold text-slate-700 transition-colors hover:bg-slate-50"
                  >
                    <Printer className="h-5 w-5 text-cyan-600" />
                    Cetak Surat
                  </button>
                )}
              </div>
            </form>
          )}

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
                      <th className="w-14 px-5 py-3 text-center font-medium text-slate-500">No.</th>
                      <th className="px-5 py-3 font-medium text-slate-500">Kode transaksi</th>
                      <th className="px-5 py-3 font-medium text-slate-500">Barang</th>
                      <th className="px-5 py-3 font-medium text-slate-500">Jumlah</th>
                      <th className="px-5 py-3 font-medium text-slate-500">Tanggal kembali</th>
                      <th className="px-5 py-3 font-medium text-slate-500">Kondisi</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {historyTable.pageItems.map((loan, index) => (
                      <tr key={loan.id}>
                        <td className="px-5 py-3 text-center text-slate-400">{historyTable.rowOffset + index + 1}</td>
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
              <div className="border-t border-slate-100 px-5 py-3">
                <TablePagination
                  page={historyTable.page}
                  lastPage={historyTable.lastPage}
                  onPageChange={historyTable.goToPage}
                  total={historyTable.total}
                  perPage={PER_PAGE}
                />
              </div>
            </section>
          )}
        </div>
      )}

      {/* Modal Unduh / Cetak / Cetak Masal */}
      <ClearanceLetterModal
        action={modalAction}
        targetBorrower={targetBorrower || selected}
        selectedBorrowersCount={selectedKeys.size}
        letter={letter}
        updateLetter={updateLetter}
        technicians={technicians}
        applyTechnician={applyTechnician}
        submitting={modalSubmitting}
        error={modalError}
        onClose={closeModal}
        onSubmit={handleModalSubmit}
      />
    </div>
  )
}

export default Clearance
