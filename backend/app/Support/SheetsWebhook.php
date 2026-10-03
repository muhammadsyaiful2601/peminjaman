<?php

namespace App\Support;

use App\Models\Student;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Tulis balik data peminjam ke Google Sheets lewat Google Apps Script.
 *
 * Sinkronisasi bawaan aplikasi hanya satu arah: spreadsheet dibaca sebagai CSV
 * terpublikasi dan isinya masuk ke database. File CSV yang dipublikasikan
 * bersifat hanya-baca, jadi perubahan di aplikasi (mis. Jabatan / Unit Kerja
 * yang baru diisi petugas) tidak bisa dikirim balik ke sana.
 *
 * Solusinya sebuah Google Apps Script kecil yang dipasang petugas di
 * spreadsheet-nya. Skripnya diekspos sebagai Web App dan URL-nya disimpan di
 * `app_settings`; setiap kali data peminjam berubah, aplikasi mengirim POST
 * berisi baris yang perlu ditulis. Skrip那边 mencari baris berdasarkan NIM/NIP
 * lalu menulis ulang kolomnya, dan menambahkan baris baru bila belum ada.
 *
 * Kegagalan webhook tidak boleh menggagalkan penyimpanan data di aplikasi:
 * spreadsheet adalah pelengkap, bukan sumber kebenaran. Karena itu semua
 * masalah jaringan/format ditoleransi dan hanya dicatat di log.
 */
class SheetsWebhook
{
    /**
     * Host yang sah untuk Web App Google Apps Script. URL di luar daftar ini
     * ditolak supaya `app_settings` tidak bisa dipakai untuk memanggil alamat
     * arbitrer dari server.
     *
     * @var array<int, string>
     */
    private const ALLOWED_HOSTS = [
        'script.google.com',
        'script.googleusercontent.com',
    ];

    /** Batas waktu menunggu Sheets (detik). */
    private const TIMEOUT_SECONDS = 15;

    /**
     * True bila URL terlihat sah sebagai Web App Google Apps Script.
     *
     * `https://script.google.com/macros/s/AKfycb.../exec` adalah bentuk yang
     * diberikan Google saat skrip disiarkan sebagai Web App.
     */
    public static function isValidUrl(string $url): bool
    {
        $parts = parse_url(trim($url));

        if ($parts === false || ($parts['scheme'] ?? '') !== 'https') {
            return false;
        }

        if (! in_array(strtolower($parts['host'] ?? ''), self::ALLOWED_HOSTS, true)) {
            return false;
        }

        // Path harus menunjuk skrip yang disiarkan: /macros/s/<ID>/exec.
        // Bentuk `<ID>/exec` tanpa prefiks `s/` juga diterima karena itulah
        // bentuk yang muncul saat skrip dipasang lewat "Deploy > New deployment".
        return (bool) preg_match('#/macros/(?:s/)?[A-Za-z0-9_\-]+/exec#', $parts['path'] ?? '');
    }

    /**
     * Normalisasi URL webhook: buang spasi serta query bawaan Google.
     *
     * @return string URL bersih, atau string kosong bila tidak sah
     */
    public static function cleanUrl(string $url): string
    {
        $url = trim($url);

        // `parse_url('')` mengembalikan array kosong (bukan null), sehingga
        // tanpa pemeriksaan awal string kosong akan terbaca sebagai `://`.
        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);

        if ($parts === false) {
            return '';
        }

        return ($parts['scheme'] ?? '').'://'.($parts['host'] ?? '').($parts['path'] ?? '');
    }

    /**
     * Kirim satu baris peminjam ke spreadsheet lewat webhook.
     *
     * @return array{ok: bool, skipped?: bool, message?: string, status?: int}
     */
    public static function push(Student $student, ?string $webhookUrl = null): array
    {
        $url = self::cleanUrl((string) $webhookUrl);

        if ($url === '' || ! self::isValidUrl($url)) {
            // Tanpa webhook yang sah, pengiriman dilewati diam-diam: petugas
            // boleh tetap memakai spreadsheet hanya untuk satu arah (baca).
            return ['ok' => false, 'skipped' => true, 'message' => 'Webhook belum dikonfigurasi.'];
        }

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withHeaders(['Accept' => 'application/json'])
                ->asJson()
                ->post($url, self::payload($student));
        } catch (ConnectionException $e) {
            Log::warning('Webhook Google Sheets gagal dihubungi: '.$e->getMessage());

            return ['ok' => false, 'message' => 'Spreadsheet tidak dapat dihubungi.'];
        } catch (\Throwable $e) {
            Log::warning('Webhook Google Sheets gagal: '.$e->getMessage());

            return ['ok' => false, 'message' => 'Gagal menulis ke spreadsheet.'];
        }

        if ($response->failed()) {
            Log::warning('Webhook Google Sheets menjawab '.$response->status().': '.$response->body());

            return [
                'ok' => false,
                'status' => $response->status(),
                'message' => 'Spreadsheet menolak permintaan (HTTP '.$response->status().').',
            ];
        }

        return ['ok' => true, 'status' => $response->status()];
    }

    /**
     * Bentuk payload yang dipahami skrip Apps Script.
     *
     * `columns` memuat seluruh isi baris (bukan hanya jabatan) supaya skrip
     * juga bisa menambahkan baris baru yang belum ada di spreadsheet, bukan
     * sekadar memperbarui satu sel.
     *
     * @return array<string, mixed>
     */
    private static function payload(Student $student): array
    {
        return [
            'action' => 'upsert',
            'type' => $student->type,
            'identity' => $student->student_id,
            'columns' => [
                self::identityLabel($student) => $student->student_id,
                'Nama' => $student->name,
                'Role' => (string) $student->role,
                'Jabatan / Unit Kerja' => (string) $student->position,
                'Email' => $student->email,
                'No. Telepon' => (string) $student->phone,
            ],
        ];
    }

    /**
     * Nama kolom identitas sesuai jenis, sama seperti template impor supaya
     * skrip bisa mencarinya di header spreadsheet.
     */
    private static function identityLabel(Student $student): string
    {
        return match ($student->type) {
            BorrowerType::DOSEN, BorrowerType::TENDIK => 'NIP',
            BorrowerType::UMUM => 'Nomor Identitas',
            default => 'NIM',
        };
    }
}
