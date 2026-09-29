import BorrowersPage from '../components/borrowers/BorrowersPage'

/**
 * Halaman Data Tendik, Dosen & Umum.
 *
 * Memakai komponen yang sama dengan halaman Data Mahasiswa, jadi penomoran,
 * paginasi, impor, dan sinkronisasi spreadsheet-nya persis sama. Bedanya hanya
 * kelompok yang ditampilkan: di sini ada tab Tendik / Dosen / Umum, dan
 * spreadsheet yang ditarik otomatis adalah milik tendik & dosen.
 */
function Employees() {
  return (
    <BorrowersPage
      allowedTypes={['tendik', 'dosen', 'umum']}
      title="Data Tendik, Dosen & Umum"
      subtitle="Data pegawai dan peminjam dari luar kampus. Tendik dan dosen punya spreadsheet Google Sheets masing-masing; peminjam umum dicatat manual lewat form."
      addHint="Pilih tab sesuai kelompok. Tendik dan dosen memakai kolom NIP, sedangkan peminjam umum memakai Nomor Identitas bebas."
    />
  )
}

export default Employees
