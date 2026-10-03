<?php

namespace App\Support;

use App\Models\AppSetting;

/**
 * Font untuk dokumen PDF (Dompdf) yang dapat dipilih pengguna.
 *
 * Dompdf tidak membaca font yang terpasang di sistem operasi seperti browser.
 * Font yang benar-benar bisa dirender hanyalah:
 *
 * 1. Font standar PDF "Base 14" (Helvetica, Times, Courier) — tersedia di semua
 *    pembaca PDF dan tidak perlu di-embed.
 * 2. Font bawaan Dompdf (keluarga DejaVu) — tersedia dan di-embed otomatis.
 *
 * Pilihan hanya berasal dari font bawaan Dompdf dan Base 14, sehingga tidak
 * memerlukan distribusi berkas font pihak ketiga.
 *
 * Pilihan pengguna disimpan di `app_settings` pada kunci `pdf_font`. Bila kunci
 * belum ada atau menunjuk font yang tak tersedia, dipakai font bawaan agar
 * dokumen PDF tetap dapat dibuat.
 */
class PdfFont
{
    /** Kunci `app_settings` untuk menyimpan pilihan font. */
    public const SETTING_KEY = 'pdf_font';

    /** Font dipakai bila pilihan pengguna tidak tersedia. */
    public const DEFAULT_KEY = 'dejavu_sans';

    /**
     * Katalog font yang bisa dipilih pengguna.
     *
     * - `family` nama keluarga font untuk CSS.
     * - `stack`  nilai `font-family` beserta font cadangan generik.
     * - `note`   keterangan singkat untuk antarmuka pengaturan.
     *
     * Hanya font yang benar-benar ada di lingkungan Dompdf yang dicantumkan.
     * Menampilkan nama font sistem (mis. Calibri atau Segoe UI) akan membuat
     * Dompdf diam-diam mengganti huruf sehingga pilihan tidak terasa berlaku.
     *
     * @var array<string, array{family: string, stack: string, note: string}>
     */
    private const CATALOG = [
        'dejavu_sans' => [
            'family' => 'DejaVu Sans',
            'stack' => "'DejaVu Sans', sans-serif",
            'note' => 'Sans-serif bawaan Dompdf. Pilihan aman yang selalu tersedia.',
        ],
        'dejavu_serif' => [
            'family' => 'DejaVu Serif',
            'stack' => "'DejaVu Serif', serif",
            'note' => 'Serif bawaan Dompdf, mirip Times New Roman.',
        ],
        'dejavu_sans_mono' => [
            'family' => 'DejaVu Sans Mono',
            'stack' => "'DejaVu Sans Mono', monospace",
            'note' => 'Monospace bawaan Dompdf, untuk kode barang dan UUID.',
        ],
        'times' => [
            'family' => 'Times',
            'stack' => "'Times', serif",
            'note' => 'Font standar PDF (Base 14), serif.',
        ],
        'helvetica' => [
            'family' => 'Helvetica',
            'stack' => "'Helvetica', sans-serif",
            'note' => 'Font standar PDF (Base 14), sans-serif.',
        ],
        'courier' => [
            'family' => 'Courier',
            'stack' => "'Courier', monospace",
            'note' => 'Font standar PDF (Base 14), monospace.',
        ],
    ];

    /**
     * Daftar font yang benar-benar siap dipakai, untuk ditampilkan pada
     * antarmuka pengaturan.
     *
     * @return array<int, array{key: string, label: string, family: string, stack: string, note: string, available: bool}>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::CATALOG as $key => $font) {
            if (! self::isReady($key)) {
                continue;
            }

            $options[] = [
                'key' => $key,
                'label' => $font['family'],
                'family' => $font['family'],
                'stack' => $font['stack'],
                'note' => $font['note'],
                'available' => true,
            ];
        }

        return $options;
    }

    /**
     * Kunci font yang sedang dipakai (selalu ada di katalog).
     */
    public static function currentKey(): string
    {
        $key = (string) AppSetting::getValue(self::SETTING_KEY, self::DEFAULT_KEY);

        return self::isReady($key) ? $key : self::DEFAULT_KEY;
    }

    /**
     * Simpan pilihan font. Kunci tak dikenal diabaikan agar tidak bisa
     * menyimpan nilai yang membuat dokumen gagal dirender.
     */
    public static function select(string $key): bool
    {
        if (! self::isReady($key)) {
            return false;
        }

        AppSetting::setValue(self::SETTING_KEY, $key);

        return true;
    }

    /**
     * Nilai `font-family` untuk dokumen PDF.
     */
    public static function stack(): string
    {
        $key = self::currentKey();

        return self::CATALOG[$key]['stack'];
    }

    /**
     * Nama keluarga font aktif, berguna untuk test dan pratinjau.
     */
    public static function family(): string
    {
        $key = self::currentKey();

        return self::CATALOG[$key]['family'];
    }

    /**
     * Font yang tersedia sudah dikenali Dompdf, jadi tidak perlu aturan
     * @font-face atau berkas font tambahan.
     */
    public static function faceCss(): string
    {
        return '';
    }

    /**
     * True bila font termasuk pilihan bawaan yang didukung Dompdf.
     */
    public static function isReady(string $key): bool
    {
        return isset(self::CATALOG[$key]);
    }
}
