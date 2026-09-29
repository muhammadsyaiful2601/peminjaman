import BorrowersPage from '../components/borrowers/BorrowersPage'

/**
 * Halaman Data Mahasiswa.
 *
 * Hanya memuat jenis "mahasiswa" (jenis terkunci, tanpa tab), tetapi seluruh
 * logikanya — penomoran, paginasi, impor, dan sinkronisasi spreadsheet —
 * identik dengan halaman pegawai karena keduanya memakai komponen yang sama.
 */
function Students() {
  return (
    <BorrowersPage
      lockedType="mahasiswa"
      title="Data Mahasiswa"
      subtitle="Simpan data mahasiswa agar pengisian peminjaman lebih cepat. Data ini juga dipakai untuk surat bebas labor."
      addHint="Kolom Jenis tidak muncul di halaman ini karena seluruh data otomatis berstatus Mahasiswa. Untuk pegawai, gunakan menu Data Tendik, Dosen & Umum."
    />
  )
}

export default Students
