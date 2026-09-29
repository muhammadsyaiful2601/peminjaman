<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\TechnicianController;
use App\Models\Technician;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Halaman Kelola Teknisi.
 *
 * Teknisi adalah pilihan penandatangan dokumen PDF (Laporan, Surat Bebas
 * Labor, Surat Peminjaman Skala Besar). Selain nama & NIP, tiap teknisi punya
 * jabatan/peran lab, nomor WhatsApp, dan tanda tangan digital (PNG/JPG/SVG).
 * Hanya satu teknisi yang boleh berstatus "Teknisi Utama".
 */
class TechnicianTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create(['role' => 'admin']);

        Sanctum::actingAs($user);

        return $user;
    }

    public function test_menyimpan_teknisi_lengkap_dengan_jabatan_dan_tanda_tangan(): void
    {
        Storage::fake('public');
        $this->admin();

        $response = $this->post('/api/technicians', [
            'name' => 'Nofa Hendrayana, S.T.',
            'nip' => '198501012010011001',
            'position' => 'Teknisi Lab Komputer / RPL',
            'whatsapp' => '081234567890',
            'is_primary' => '1',
            'signature' => UploadedFile::fake()->image('tanda-tangan.png'),
        ]);

        $response->assertCreated()->assertJsonPath('technician.position', 'Teknisi Lab Komputer / RPL');

        $technician = Technician::firstOrFail();
        $this->assertSame('Nofa Hendrayana, S.T.', $technician->name);
        $this->assertSame('Teknisi Lab Komputer / RPL', $technician->position);
        $this->assertSame('081234567890', $technician->whatsapp);
        $this->assertTrue($technician->is_primary);
        $this->assertNotNull($technician->signature_path);
        Storage::disk('public')->assertExists($technician->signature_path);

        // Tanda tangan ikut diekspos agar frontend bisa langsung menampilkan
        // pratinjaunya tanpa menyusun URL sendiri.
        $this->assertSame('/storage/'.$technician->signature_path, $technician->signature_url);
    }

    public function test_hanya_satu_teknisi_boleh_menjadi_teknisi_utama(): void
    {
        $this->admin();

        Technician::create(['name' => 'Teknisi Pertama', 'nip' => '1001', 'is_primary' => true]);
        Technician::create(['name' => 'Teknisi Kedua', 'nip' => '1002']);

        $this->post('/api/technicians', [
            'name' => 'Teknisi Ketiga',
            'nip' => '1003',
            'is_primary' => '1',
        ])->assertCreated();

        $this->assertSame(1, Technician::where('is_primary', true)->count());
        $this->assertTrue(Technician::where('nip', '1003')->firstOrFail()->is_primary);

        // Teknisi utama diletakkan paling atas pada daftar.
        $this->getJson('/api/technicians')->assertJsonPath('0.nip', '1003');
    }

    public function test_memperbarui_teknisi_dan_mengganti_tanda_tangan(): void
    {
        Storage::fake('public');
        $this->admin();

        $technician = Technician::create(['name' => 'Nama Lama', 'nip' => '2001']);
        $pathLama = UploadedFile::fake()->image('lama.png')->store('signatures', 'public');
        $technician->update(['signature_path' => $pathLama]);

        $response = $this->put('/api/technicians/'.$technician->id, [
            'name' => 'Nofa Hendrayana, S.T.',
            'nip' => '2001',
            'position' => 'Teknisi Lab Jaringan',
            'whatsapp' => '081200000000',
            'signature' => UploadedFile::fake()->image('baru.png'),
        ]);

        $response->assertOk()->assertJsonPath('technician.position', 'Teknisi Lab Jaringan');

        $technician->refresh();
        $this->assertSame('Nofa Hendrayana, S.T.', $technician->name);
        $this->assertNotSame($pathLama, $technician->signature_path);
        Storage::disk('public')->assertExists($technician->signature_path);
        Storage::disk('public')->assertMissing($pathLama);
    }

    public function test_tanda_tangan_lama_dapat_dihapus_saat_memperbarui(): void
    {
        Storage::fake('public');
        $this->admin();

        $technician = Technician::create(['name' => 'Nofa', 'nip' => '3001']);
        $technician->update([
            'signature_path' => UploadedFile::fake()->image('lama.png')->store('signatures', 'public'),
        ]);
        $signature = $technician->signature_path;

        $this->put('/api/technicians/'.$technician->id, [
            'name' => 'Nofa',
            'nip' => '3001',
            'remove_signature' => '1',
        ])->assertOk();

        $this->assertNull($technician->fresh()->signature_path);
        Storage::disk('public')->assertMissing($signature);
    }

    public function test_menghapus_teknisi_ikut_membuang_berkas_tanda_tangan(): void
    {
        Storage::fake('public');
        $this->admin();

        $technician = Technician::create(['name' => 'Nofa', 'nip' => '4001']);
        $technician->update([
            'signature_path' => UploadedFile::fake()->image('hapus.png')->store('signatures', 'public'),
        ]);
        $signature = $technician->signature_path;

        $this->deleteJson('/api/technicians/'.$technician->id)->assertOk();

        $this->assertNull(Technician::find($technician->id));
        Storage::disk('public')->assertMissing($signature);
    }

    public function test_menolak_data_tidak_lengkap_dan_nip_ganda(): void
    {
        $this->admin();

        $this->postJson('/api/technicians', [])->assertJsonValidationErrors(['name', 'nip']);

        Technician::create(['name' => 'Nofa', 'nip' => '5001']);

        $this->postJson('/api/technicians', ['name' => 'Nofa', 'nip' => '5001'])
            ->assertJsonValidationErrors(['nip']);
    }

    public function test_menolak_tanda_tangan_bukan_berkas_gambar(): void
    {
        Storage::fake('public');
        $this->admin();

        // Header Accept membuat validasi gagal dikembalikan sebagai JSON 422
        // (bukan exception) walau permintaan berupa unggahan berkas.
        $this->post('/api/technicians', [
            'name' => 'Nofa',
            'nip' => '6001',
            'signature' => UploadedFile::fake()->create('dokumen.pdf', 32, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertJsonValidationErrors(['signature']);
    }

    public function test_kelola_teknisi_hanya_untuk_admin(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'assistant']));

        $technician = Technician::create(['name' => 'Nofa', 'nip' => '7001']);

        $this->postJson('/api/technicians', ['name' => 'Nofa', 'nip' => '7002'])->assertForbidden();
        $this->deleteJson('/api/technicians/'.$technician->id)->assertForbidden();
    }

    /**
     * Halaman & daftar teknisi harus tetap bisa dipakai walau migrasi kolom
     * profil belum dijalankan (mis. web hosting yang belum `artisan migrate`).
     * Tanpa ini, `order by is_primary` membuat seluruh halaman gagal 500.
     */
    public function test_halaman_teknisi_tetap_berjalan_saat_migrasi_belum_dijalankan(): void
    {
        $this->admin();

        // Simulasikan database yang belum menjalankan migrasi kolom profil.
        Schema::table('technicians', function (Blueprint $table) {
            $table->dropColumn(['position', 'whatsapp', 'signature_path', 'is_primary']);
        });
        TechnicianController::flushColumnCache();

        Technician::create(['name' => 'Budi Santoso', 'nip' => '9001']);
        Technician::create(['name' => 'Andi Wijaya', 'nip' => '9002']);

        $this->getJson('/api/technicians')
            ->assertOk()
            ->assertJsonPath('0.name', 'Andi Wijaya');

        // Menyimpan teknisi tetap berhasil (fitur profil menunggu migrasi).
        $this->postJson('/api/technicians', [
            'name' => 'Teknisi Baru',
            'nip' => '9003',
            'position' => 'Teknisi Lab Komputer',
        ])->assertCreated();

        $this->assertDatabaseHas('technicians', ['nip' => '9003', 'name' => 'Teknisi Baru']);
    }
}

