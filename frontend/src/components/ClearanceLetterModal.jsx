import { Download, Printer, X } from 'lucide-react'

const purposeOptions = [
  'Persyaratan bebas pustaka',
  'Persyaratan pengambilan ijazah',
  'Persyaratan yudisium / wisuda',
  'Persyaratan pindah / cuti studi',
  'Persyaratan Kerja Praktik / magang',
]

export function ClearanceLetterModal({
  action,
  targetBorrower,
  selectedBorrowersCount = 0,
  letter,
  updateLetter,
  technicians = [],
  applyTechnician,
  submitting = false,
  error = '',
  onClose,
  onSubmit,
}) {
  if (!action) return null
  const isBulk = action === 'bulk-print'

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm">
      <div className="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-xl">
        <div className="flex items-start justify-between border-b border-slate-100 pb-4">
          <div>
            <h3 className="text-lg font-bold text-slate-900">
              {action === 'download'
                ? 'Unduh Surat Bebas Labor'
                : (action === 'print' ? 'Cetak Surat Bebas Labor' : `Cetak Masal (${selectedBorrowersCount} Mahasiswa)`)}
            </h3>
            <p className="mt-0.5 text-xs text-slate-500">
              {isBulk
                ? `Mencetak surat bebas labor untuk ${selectedBorrowersCount} mahasiswa terpilih.`
                : `Peminjam: ${targetBorrower?.name || '-'}`}
            </p>
          </div>
          <button type="button" onClick={onClose} className="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
            <X className="h-5 w-5" />
          </button>
        </div>

        {error && (
          <div className="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-700">
            {error}
          </div>
        )}

        <form onSubmit={onSubmit} className="mt-4 space-y-3">
          <label className="block text-xs font-medium text-slate-700">
            Keperluan surat <span className="text-red-600">*</span>
            <input
              required
              name="purpose"
              list="modal-clearance-purpose-list"
              value={letter.purpose}
              onChange={updateLetter}
              placeholder="Contoh: Persyaratan bebas pustaka"
              className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500"
            />
            <datalist id="modal-clearance-purpose-list">
              {purposeOptions.map((opt) => <option key={opt} value={opt} />)}
            </datalist>
          </label>

          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <label className="block text-xs font-medium text-slate-700">
              Tanggal surat
              <input
                type="date"
                name="letter_date"
                value={letter.letter_date}
                onChange={updateLetter}
                className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500"
              />
            </label>
            <label className="block text-xs font-medium text-slate-700">
              Laboratorium
              <input
                name="laboratory"
                value={letter.laboratory}
                onChange={updateLetter}
                placeholder="Contoh: Lab Komputer"
                className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500"
              />
            </label>
          </div>

          {technicians.length > 0 && (
            <label className="block text-xs font-medium text-slate-700">
              Pilih teknisi penandatangan
              <select
                onChange={applyTechnician}
                value={technicianId}
                className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500"
              >
                <option value="">Pilih teknisi</option>
                {technicians.map((t) => (
                  <option key={t.id} value={t.id}>
                    {t.name} - NIP. {t.nip}{t.signature_path ? ' (punya tanda tangan)' : ''}
                  </option>
                ))}
              </select>
            </label>
          )}

          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <label className="block text-xs font-medium text-slate-700">
              Nama penandatangan <span className="text-red-600">*</span>
              <input
                required
                name="signatory_name"
                value={letter.signatory_name}
                onChange={updateLetter}
                placeholder="Nama petugas"
                className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500"
              />
            </label>
            <label className="block text-xs font-medium text-slate-700">
              NIP penandatangan <span className="text-red-600">*</span>
              <input
                required
                name="signatory_nip"
                value={letter.signatory_nip}
                onChange={updateLetter}
                placeholder="NIP petugas"
                className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500"
              />
            </label>
          </div>

          <div className="mt-5 flex justify-end gap-2 pt-3 border-t border-slate-100">
            <button
              type="button"
              onClick={onClose}
              disabled={submitting}
              className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
            >
              Batal
            </button>
            <button
              type="submit"
              disabled={submitting}
              className="inline-flex items-center gap-2 rounded-lg bg-cyan-600 px-4 py-2 text-sm font-medium text-white hover:bg-cyan-700 disabled:opacity-50"
            >
              {action === 'download' ? (
                <>
                  <Download className="h-4 w-4" />
                  {submitting ? 'Mengunduh...' : 'Unduh PDF'}
                </>
              ) : (
                <>
                  <Printer className="h-4 w-4" />
                  {submitting ? 'Menyiapkan...' : (isBulk ? `Cetak ${selectedBorrowersCount} Surat` : 'Cetak Surat')}
                </>
              )}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}
