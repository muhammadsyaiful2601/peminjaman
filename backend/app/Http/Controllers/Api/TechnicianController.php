<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Technician;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

class TechnicianController extends Controller
{
    /** Folder tanda tangan digital pada disk "public". */
    private const SIGNATURE_DIR = 'signatures';

    /**
     * Cache ketersediaan kolom profil per-request.
     *
     * @var array<string, bool>
     */
    private static array $columns = [];

    /**
     * Daftar teknisi, teknisi utama lebih dulu.
     *
     * Kolom `is_primary` baru ada setelah migrasi dijalankan. Selama migrasi
     * belum dijalankan (mis. web hosting yang belum `php artisan migrate`),
     * urutan "teknisi utama" dilewati daripada membuat halaman ini gagal 500.
     */
    public function index()
    {
        $query = Technician::query();

        if ($this->hasColumn('is_primary')) {
            $query->orderByDesc('is_primary');
        }

        return response()->json($query->orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules($request));

        $technician = Technician::create($this->attributes($validated));

        $this->syncSignature($request, $technician);
        $this->syncPrimary($technician, $request->boolean('is_primary'));

        return response()->json([
            'message' => 'Teknisi berhasil ditambahkan',
            'technician' => $technician->fresh(),
        ], 201);
    }

    public function update(Request $request, Technician $technician)
    {
        $validated = $request->validate($this->rules($request));

        $technician->update($this->attributes($validated));

        $this->syncSignature($request, $technician);
        $this->syncPrimary($technician, $request->boolean('is_primary'));

        return response()->json([
            'message' => 'Teknisi berhasil diperbarui',
            'technician' => $technician->fresh(),
        ]);
    }

    public function destroy(Technician $technician)
    {
        // Berkas tanda tangan ikut dibersihkan agar folder unggahan tidak
        // menumpuk file yang tidak lagi dirujuk.
        if ($technician->signature_path) {
            Storage::disk('public')->delete($technician->signature_path);
        }

        $technician->delete();

        return response()->json(['message' => 'Teknisi berhasil dihapus']);
    }

    /**
     * Aturan validasi. NIP tetap unik (kecuali saat mengubah teknisi itu
     * sendiri), dan tanda tangan dibatasi gambar ringan atau vektor SVG.
     *
     * @return array<string, array<int, mixed>>
     */
    private function rules(Request $request): array
    {
        $technician = $request->route('technician');
        $id = $technician instanceof Technician ? $technician->id : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'nip' => ['required', 'string', 'max:100', Rule::unique('technicians', 'nip')->ignore($id)],
            'position' => ['nullable', 'string', 'max:160'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'is_primary' => ['sometimes', 'boolean'],
            'remove_signature' => ['sometimes', 'boolean'],
            'signature' => ['nullable', 'file', 'mimes:png,jpg,jpeg,svg', 'max:2048'],
        ];
    }

    /**
     * Kolom yang benar-benar ada di database.
     *
     * Kolom profil (jabatan/WhatsApp) belum tersedia bila migrasi belum
     * dijalankan; datanya dilewati agar teknisi tetap bisa disimpan.
     *
     * @return array<string, string|null>
     */
    private function attributes(array $validated): array
    {
        $attributes = [
            'name' => $validated['name'],
            'nip' => $validated['nip'],
        ];

        foreach (['position', 'whatsapp'] as $column) {
            if ($this->hasColumn($column)) {
                $attributes[$column] = $validated[$column] ?? null;
            }
        }

        return $attributes;
    }

    /** Simpan tanda tangan baru / hapus tanda tangan lama sesuai pilihan form. */
    private function syncSignature(Request $request, Technician $technician): void
    {
        if (! $this->hasColumn('signature_path')) {
            return;
        }

        if ($request->boolean('remove_signature')) {
            $this->deleteSignature($technician);

            return;
        }

        if (! $request->hasFile('signature')) {
            return;
        }

        $this->deleteSignature($technician);

        $technician->update([
            'signature_path' => $request->file('signature')->store(self::SIGNATURE_DIR, 'public'),
        ]);
    }

    private function deleteSignature(Technician $technician): void
    {
        if ($technician->signature_path) {
            Storage::disk('public')->delete($technician->signature_path);
            $technician->update(['signature_path' => null]);
        }
    }

    /**
     * Pastikan hanya satu teknisi berstatus utama: menjadi utama berarti
     * teknisi sebelumnya turun ke status biasa.
     */
    private function syncPrimary(Technician $technician, bool $isPrimary): void
    {
        if (! $isPrimary || ! $this->hasColumn('is_primary')) {
            return;
        }

        Technician::where('id', '!=', $technician->id)->update(['is_primary' => false]);
        $technician->update(['is_primary' => true]);
    }

    /**
     * True bila tabel `technicians` sudah punya kolom tertentu.
     *
     * Hasilnya disimpan per-request karena beberapa halaman (Laporan, Bebas
     * Labor) memuat daftar teknisi berulang kali.
     */
    private function hasColumn(string $column): bool
    {
        if (isset(self::$columns[$column])) {
            return self::$columns[$column];
        }

        try {
            return self::$columns[$column] = Schema::hasColumn('technicians', $column);
        } catch (Throwable $e) {
            Log::warning('Struktur tabel technicians tidak dapat diperiksa: '.$e->getMessage());

            return self::$columns[$column] = false;
        }
    }

    /**
     * Bersihkan cache ketersediaan kolom.
     *
     * Dipanggil tes karena cache bersifat statis (satu proses), sementara
     * tiap tes dapat memakai skema database yang berbeda.
     */
    public static function flushColumnCache(): void
    {
        self::$columns = [];
    }
}