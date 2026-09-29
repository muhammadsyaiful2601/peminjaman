/**
 * Kategori peminjam, mencerminkan `App\Support\BorrowerType` di backend.
 *
 * Aplikasi semula hanya melayani mahasiswa, lalu diperluas untuk pegawai:
 * tendik, dosen, dan peminjam dari luar kampus (umum).
 */
export const BORROWER_TYPES = [
  { value: 'mahasiswa', label: 'Mahasiswa' },
  { value: 'tendik', label: 'Tendik' },
  { value: 'dosen', label: 'Dosen' },
  { value: 'umum', label: 'Umum' },
]

export const DEFAULT_BORROWER_TYPE = 'mahasiswa'

/** Warna badge jenis peminjam di tabel dan daftar. */
const BADGE_CLASSES = {
  mahasiswa: 'bg-blue-50 text-blue-700 ring-blue-200',
  tendik: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
  dosen: 'bg-violet-50 text-violet-700 ring-violet-200',
  umum: 'bg-amber-50 text-amber-700 ring-amber-200',
}

/** Nilai aman: data/transaksi lama tanpa jenis dibaca sebagai mahasiswa. */
export function normalizeBorrowerType(value) {
  const found = BORROWER_TYPES.some((type) => type.value === value)

  return found ? value : DEFAULT_BORROWER_TYPE
}

export function borrowerTypeLabel(value) {
  const type = normalizeBorrowerType(value)

  return BORROWER_TYPES.find((item) => item.value === type)?.label || 'Mahasiswa'
}

export function borrowerTypeBadgeClass(value) {
  return BADGE_CLASSES[normalizeBorrowerType(value)]
}

/**
 * Nama kolom identitas sesuai jenis peminjam: NIM untuk mahasiswa, NIP untuk
 * pegawai, dan "Nomor Identitas" untuk peminjam umum.
 */
export function identityLabel(value) {
  switch (normalizeBorrowerType(value)) {
    case 'tendik':
    case 'dosen':
      return 'NIP'
    case 'umum':
      return 'Nomor Identitas'
    default:
      return 'NIM'
  }
}
