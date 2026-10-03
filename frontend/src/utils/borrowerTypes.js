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

/**
 * Jenis peminjam yang punya spreadsheet sinkronisasi sendiri, dengan logika
 * yang persis sama seperti mahasiswa: tautan Google Sheets terpublikasi,
 * impor otomatis berkala, dan penanda waktu sinkron terakhir.
 *
 * Peminjam umum tidak termasuk — jumlahnya sedikit dan dicatat manual.
 * Cerminan `App\Support\BorrowerType::SPREADSHEET_TYPES` di backend.
 */
export const SPREADSHEET_BORROWER_TYPES = BORROWER_TYPES.filter((type) => (
  ['mahasiswa', 'tendik', 'dosen'].includes(type.value)
))

/**
 * Kunci localStorage untuk URL spreadsheet tiap kelompok.
 *
 * Mahasiswa memakai nama lamanya (`student_sync_csv_url`) agar browser yang
 * sudah pernah menyimpan URL tidak perlu menautkan ulang spreadsheet.
 *
 * Tendik & Dosen memakai satu kunci bersama (`employee_sync_csv_url`) karena
 * keduanya memang membaca spreadsheet yang sama.
 */
export function syncStorageKey(type) {
  const value = normalizeBorrowerType(type)

  if (value === 'mahasiswa') return 'student_sync_csv_url'

  return 'employee_sync_csv_url'
}

export function hasSpreadsheetSupport(type) {
  return SPREADSHEET_BORROWER_TYPES.some((item) => item.value === normalizeBorrowerType(type))
}

/**
 * Kunci localStorage untuk URL webhook tulis-balik. Tendik & Dosen memakai satu
 * kunci bersama karena spreadsheetnya juga satu; mahasiswa terpisah.
 *
 * Cerminan `App\Support\BorrowerType::WEBHOOK_KEYS` di backend.
 */
export function webhookStorageKey(type) {
  return normalizeBorrowerType(type) === 'mahasiswa'
    ? 'student_sheets_webhook_url'
    : 'employee_sheets_webhook_url'
}

/**
 * Kelompok pembacaan spreadsheet.
 *
 * Setiap kelompok dibaca satu kali per siklus sinkronisasi. Tendik & Dosen satu
 * kelompok karena memakai spreadsheet yang sama — tanpa ini keduanya akan
 * menarik CSV yang sama dua kali dan menghitung datanya dobel.
 *
 * `forcedType` diisi hanya bila seluruh baris spreadsheet pasti satu jenis
 * (mahasiswa). Untuk kelompok gabungan nilainya null sehingga kolom "Jenis"
 * pada sheet menentukan jenis tiap baris.
 *
 * @param {string[]} [types] Cakupan jenis halaman pemanggil.
 * @returns {Array<{key: string, label: string, types: string[], forcedType: string|null}>}
 */
export function spreadsheetSyncGroups(types) {
  const pool = (types || SPREADSHEET_BORROWER_TYPES.map((item) => item.value))
    .map(normalizeBorrowerType)
    .filter((value) => value !== 'umum')

  const groups = []

  if (pool.includes('mahasiswa')) {
    groups.push({
      key: 'mahasiswa',
      label: 'Mahasiswa',
      types: ['mahasiswa'],
      forcedType: 'mahasiswa',
    })
  }

  const shared = pool.filter((value) => value === 'tendik' || value === 'dosen')
  if (shared.length > 0) {
    groups.push({
      key: 'employee',
      label: 'Tendik & Dosen',
      types: ['tendik', 'dosen'],
      forcedType: null,
    })
  }

  return groups
}

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

export function employeeTypeFromPosition(position) {
  return String(position || '').toLowerCase().includes('dosen') ? 'dosen' : 'tendik'
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
