<?php

namespace App\Support;

/**
 * Kategori peminjam.
 *
 * Aplikasi semula hanya melayani mahasiswa, lalu diperluas untuk pegawai:
 * tendik (tenaga pendidik & kependidikan), dosen, dan peminjam umum
 * (peminjam dari luar Politeknik Negeri Padang). Data semuanya tetap
 * disimpan pada tabel `students` agar seluruh alur yang sudah ada
 * (pencocokan surat bebas labor, form peminjaman, pencarian) ikut memakai
 * data pegawai tanpa perubahan.
 */
class BorrowerType
{
    public const MAHASISWA = 'mahasiswa';

    public const TENDIK = 'tendik';

    public const DOSEN = 'dosen';

    public const UMUM = 'umum';

    /** Semua jenis yang sah, urut sesuai urutan tab di antarmuka. */
    public const ALL = [
        self::MAHASISWA,
        self::TENDIK,
        self::DOSEN,
        self::UMUM,
    ];

    /** Label yang ditampilkan ke petugas. */
    public const LABELS = [
        self::MAHASISWA => 'Mahasiswa',
        self::TENDIK => 'Tendik',
        self::DOSEN => 'Dosen',
        self::UMUM => 'Umum',
    ];

    /**
     * Istilah lain yang sering dipakai pada spreadsheet peminjam. Daftar
     * ditulis dalam bentuk "sudah dibersihkan" (huruf kecil tanpa spasi),
     * sama dengan `StudentController::normalizeColumn()`.
     *
     * @var array<string, string>
     */
    private const ALIASES = [
        'mahasiswa' => self::MAHASISWA,
        'mhs' => self::MAHASISWA,
        'student' => self::MAHASISWA,
        'siswa' => self::MAHASISWA,
        'npm' => self::MAHASISWA,
        'cicip' => self::MAHASISWA,

        'tendik' => self::TENDIK,
        'tenagapendidikan' => self::TENDIK,
        'kependidikan' => self::TENDIK,
        'eddik' => self::TENDIK,
        'tgskependidikan' => self::TENDIK,
        'staffpendidikan' => self::TENDIK,
        'administrasi' => self::TENDIK,
        'staf' => self::TENDIK,

        'dosen' => self::DOSEN,
        'dsn' => self::DOSEN,
        'pengajar' => self::DOSEN,
        'lecturer' => self::DOSEN,
        'instruktur' => self::DOSEN,
        'dosenpeneliti' => self::DOSEN,

        'umum' => self::UMUM,
        'umumdanlainnya' => self::UMUM,
        'lainnya' => self::UMUM,
        'masyarakat' => self::UMUM,
        'peminjamluar' => self::UMUM,
        'luarkampus' => self::UMUM,
        'other' => self::UMUM,
        'nonpnp' => self::UMUM,
    ];

    /**
     * Bersihkan teks jenis sama seperti normalisasi nama kolom spreadsheet.
     */
    public static function normalizeColumn(string $value): string
    {
        return (string) preg_replace('/[^a-z0-9]/', '', mb_strtolower(trim($value)));
    }

    /**
     * Terjemahkan bebas teks (mis. isi sel spreadsheet) menjadi jenis yang sah.
     * Mengembalikan null bila tidak dikenali supaya pemanggil bisa memberi
     * pesan yang jelas alih-alih diam-diam menyimpan jenis salah.
     */
    public static function fromText(?string $value): ?string
    {
        $key = self::normalizeColumn((string) $value);

        if ($key === '') {
            return null;
        }

        if (in_array($key, self::ALL, true)) {
            return $key;
        }

        return self::ALIASES[$key] ?? null;
    }

    /**
     * Nilai aman untuk disimpan. Data lama (sebelum kolom `type` ada) dibaca
     * sebagai mahasiswa supaya tidak suddenly tampil sebagai "Umum".
     */
    public static function clean(mixed $value): string
    {
        $type = is_string($value) ? self::fromText($value) : null;

        return $type ?? self::MAHASISWA;
    }

    public static function label(mixed $value): string
    {
        $type = self::clean($value);

        return self::LABELS[$type];
    }

    /**
     * Daftar pilihan untuk dropdown/validasi, mis.
     * `Rule::in(array_keys(BorrowerType::options()))`.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return self::LABELS;
    }
}
