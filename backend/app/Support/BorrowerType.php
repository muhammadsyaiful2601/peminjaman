<?php

namespace App\Support;

use App\Models\AppSetting;

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

    /**
     * Kategorikan pegawai dari jabatan/unit kerja yang ditampilkan di bawah
     * nama. Hanya teks yang memuat kata "dosen" yang masuk kategori dosen.
     */
    public static function employeeTypeFromPosition(?string $position): string
    {
        return str_contains(self::normalizeColumn((string) $position), 'dosen')
            ? self::DOSEN
            : self::TENDIK;
    }

    public static function label(mixed $value): string
    {
        $type = self::clean($value);

        return self::LABELS[$type];
    }

    /**
     * Kelompok sinkronisasi: satu spreadsheet dapat melayani satu jenis
     * (mahasiswa) atau dua jenis sekaligus (tendik + dosen).
     *
     * Tendik dan Dosen sengaja disatukan menjadi satu spreadsheet
     * `employee`, sedangkan mahasiswa tetap punya spreadsheet sendiri supaya
     * data mahasiswa tidak pernah ikut tersentuh oleh perubahan daftar
     * pegawai.
     */
    public const GROUP_MAHASISWA = 'mahasiswa';

    public const GROUP_EMPLOYEE = 'employee';

    /** Kategori internal yang datanya berada pada spreadsheet pegawai bersama. */
    public const SHARED_SHEET_TYPES = [
        self::TENDIK,
        self::DOSEN,
    ];

    /**
     * Kunci `app_settings` untuk URL CSV Google Sheets tiap kelompok.
     *
     * Kunci mahasiswa sengaja memakai nama lama (`student_sync_csv_url`)
     * supaya instalasi yang sudah menyimpan URL tidak kehilangan
     * konfigurasinya saat fitur ini ditambahkan.
     *
     * Tendik & Dosen memakai satu kunci bersama (`employee_sync_csv_url`)
     * karena keduanya ditautkan ke spreadsheet yang sama.
     *
     * @var array<string, string>
     */
    private const SYNC_URL_KEYS = [
        self::GROUP_MAHASISWA => 'student_sync_csv_url',
        self::GROUP_EMPLOYEE => 'employee_sync_csv_url',
    ];

    /** @var array<string, string> */
    private const SYNC_LAST_KEYS = [
        self::GROUP_MAHASISWA => 'student_sync_last_at',
        self::GROUP_EMPLOYEE => 'employee_sync_last_at',
    ];

    /**
     * Kunci `app_settings` untuk URL Google Apps Script per kelompok. Webhook ini
     * dipakai menulis balik perubahan dari aplikasi ke spreadsheet, sesuatu
     * yang tidak bisa dilakukan lewat CSV terpublikasi yang hanya-baca.
     *
     * @var array<string, string>
     */
    private const WEBHOOK_KEYS = [
        self::GROUP_MAHASISWA => 'student_sheets_webhook_url',
        self::GROUP_EMPLOYEE => 'employee_sheets_webhook_url',
    ];

    /**
     * Kunci `app_settings` untuk waktu penulisan terakhir yang berhasil, agar
     * petugas bisa melihat apakah jabatan yang ia ubah sudah sampai ke
     * spreadsheet.
     *
     * @var array<string, string>
     */
    private const WEBHOOK_LAST_KEYS = [
        self::GROUP_MAHASISWA => 'student_sheets_push_last_at',
        self::GROUP_EMPLOYEE => 'employee_sheets_push_last_at',
    ];

    /**
     * Kunci lama per jenis, dipakai sebagai cadangan baca supaya instalasi
     * yang sebelum spreadsheet gabungan diperluas tidak langsung kehilangan
     * URL yang sudah ditautkan. Kunci baru tetap jadi satu-satunya yang
     * ditulis.
     *
     * @var array<string, array<int, string>>
     */
    private const LEGACY_URL_KEYS = [
        self::TENDIK => ['tendik_sync_csv_url'],
        self::DOSEN => ['dosen_sync_csv_url'],
    ];

    /**
     * Jenis peminjam yang punya spreadsheet sinkronisasi.
     *
     * Peminjam umum tidak termasuk: jumlahnya sedikit dan biasanya dicatat
     * manual, jadi tidak perlu tautan spreadsheet per kelompok.
     */
    public const SPREADSHEET_TYPES = [
        self::MAHASISWA,
        self::TENDIK,
        self::DOSEN,
    ];

    /**
     * Kelompok sinkronisasi sebuah jenis: `mahasiswa` atau `employee`.
     * Null bila jenis ini tidak punya spreadsheet.
     */
    public static function syncGroup(?string $type): ?string
    {
        $clean = self::clean($type);

        if ($clean === self::MAHASISWA) {
            return self::GROUP_MAHASISWA;
        }

        return in_array($clean, self::SHARED_SHEET_TYPES, true)
            ? self::GROUP_EMPLOYEE
            : null;
    }

    /**
     * True bila spreadsheet jenis ini dipakai bersama beberapa kategori
     * pegawai. Role pada setiap baris menentukan kategori di aplikasi.
     */
    public static function usesSharedSheet(?string $type): bool
    {
        return self::syncGroup($type) === self::GROUP_EMPLOYEE;
    }

    /**
     * Semua jenis yang dilayani satu spreadsheet. Untuk mahasiswa hanya
     * dirinya sendiri; untuk Tendik/Dosen keduanya berada di sheet yang sama.
     *
     * @return array<int, string>
     */
    public static function sheetTypes(?string $type): array
    {
        return self::usesSharedSheet($type)
            ? self::SHARED_SHEET_TYPES
            : [self::clean($type)];
    }

    /**
     * Jenis yang dipaksakan ke seluruh baris spreadsheet.
     *
     * Sheet gabungan meNull, karena memaksa semua baris menjadi satu jenis akan
     * membuat Dosen salah label sebagai Tendik.
     */
    public static function forcedType(?string $type): ?string
    {
        return self::usesSharedSheet($type) ? null : self::clean($type);
    }

    /**
     * Kunci `app_settings` untuk URL spreadsheet; null bila jenis ini tidak punya.
     * Tendik & Dosen sama-sama memakai satu kunci karena spreadsheetnya sama.
     */
    public static function syncUrlKey(?string $type): ?string
    {
        $group = self::syncGroup($type);

        return $group === null ? null : self::SYNC_URL_KEYS[$group];
    }

    /** Kunci `app_settings` untuk waktu sinkronisasi terakhir. */
    public static function syncLastKey(?string $type): ?string
    {
        $group = self::syncGroup($type);

        return $group === null ? null : self::SYNC_LAST_KEYS[$group];
    }

    /**
     * Kunci `app_settings` untuk URL webhook tulis-balik; null bila jenis ini
     * tidak punya spreadsheet.
     */
    public static function webhookKey(?string $type): ?string
    {
        $group = self::syncGroup($type);

        return $group === null ? null : self::WEBHOOK_KEYS[$group];
    }

    /** Kunci `app_settings` untuk waktu penulisan terakhir ke spreadsheet. */
    public static function webhookLastKey(?string $type): ?string
    {
        $group = self::syncGroup($type);

        return $group === null ? null : self::WEBHOOK_LAST_KEYS[$group];
    }

    /**
     * URL spreadsheet yang tersimpan untuk sebuah jenis.
     *
     * Bila kunci gabungan masih kosong, kunci lama per jenis dibaca sebagai
     * cadangan supaya instalasi lama tidak kehilangan tautan yang sudah
     * disimpan. Penulisan selalu memakai kunci baru (lihat `syncUrlKey`).
     */
    public static function syncUrl(?string $type): string
    {
        $key = self::syncUrlKey($type);

        if ($key === null) {
            return '';
        }

        $url = trim((string) AppSetting::getValue($key, ''));

        if ($url !== '') {
            return $url;
        }

        foreach (self::LEGACY_URL_KEYS[self::clean($type)] ?? [] as $legacyKey) {
            $legacy = trim((string) AppSetting::getValue($legacyKey, ''));

            if ($legacy !== '') {
                return $legacy;
            }
        }

        return '';
    }

    /**
     * URL webhook tersimpan untuk sebuah jenis, atau string kosong bila belum
     * dikonfigurasi.
     */
    public static function webhookUrl(?string $type): string
    {
        $key = self::webhookKey($type);

        return $key === null ? '' : (string) AppSetting::getValue($key, '');
    }

    public static function supportsSpreadsheet(?string $type): bool
    {
        return self::syncUrlKey($type) !== null;
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
