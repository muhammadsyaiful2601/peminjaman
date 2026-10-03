<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Gabungkan URL spreadsheet Tendik dan Dosen.
 *
 * Sebelumnya, setiap kelompok menggunakan kunci tersendiri
 * (`tendik_sync_csv_url` dan `dosen_sync_csv_url`). Keduanya kini menggunakan
 * satu kunci bersama (`employee_sync_csv_url`) untuk spreadsheet gabungan.
 *
 * URL lama tidak dihapus. Jika kunci gabungan masih kosong, URL lama tetap
 * digunakan sebagai cadangan baca (lihat `BorrowerType::syncUrl()`) sampai
 * petugas menghubungkan spreadsheet gabungan. Jika kedua URL lama sama, nilai
 * tersebut disalin ke kunci gabungan agar petugas tidak perlu memasukkannya
 * kembali.
 */
return new class extends Migration
{
    public function up(): void
    {
        $shared = trim((string) $this->read('employee_sync_csv_url', ''));

        if ($shared !== '') {
            return;
        }

        $tendik = trim((string) $this->read('tendik_sync_csv_url', ''));
        $dosen = trim((string) $this->read('dosen_sync_csv_url', ''));

        // Salin hanya jika URL sama atau salah satunya belum diisi. URL berbeda
        // menunjukkan sumber yang berbeda; jangan memilih salah satunya
        // secara otomatis.
        if ($tendik === '' && $dosen === '') {
            return;
        }

        if ($tendik !== '' && $dosen !== '' && $tendik !== $dosen) {
            return;
        }

        $this->write('employee_sync_csv_url', $tendik !== '' ? $tendik : $dosen);
    }

    public function down(): void
    {
        // Kunci lama sengaja tidak dihapus/diubah: membatalkan penggabungan
        // cukup dengan mengisi ulang URL per kelompok lewat aplikasi.
    }

    private function read(string $key, string $default): ?string
    {
        try {
            return DB::table('app_settings')->where('key', $key)->value('value') ?? $default;
        } catch (\Throwable) {
            // Tabel app_settings dibuat oleh migrasi lain.
            return $default;
        }
    }

    private function write(string $key, string $value): void
    {
        DB::table('app_settings')->updateOrInsert(['key' => $key], [
            'value' => $value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};