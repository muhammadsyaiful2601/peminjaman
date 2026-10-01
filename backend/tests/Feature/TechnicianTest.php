<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\TechnicianController;
use App\Models\Item;
use App\Models\Loan;
use App\Models\Student;
use App\Models\Technician;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Halaman Kelola Teknisi.
 *
 * Teknisi adalah pilihan penandatangan dokumen PDF (Laporan, Surat Bebas
 * Labor, Surat Peminjaman Skala Besar). Selain nama & NIP, tiap teknisi punya
 * jabatan/peran lab, nomor WhatsApp, dan tanda tangan digital (PNG/JPG).
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

    public function test_tanda_tangan_digital_masuk_ke_laporan_dan_surat_pdf(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $technician = Technician::create([
            'name' => 'Nofa Hendrayana, S.T.',
            'nip' => '198501012010011001',
            'signature_path' => UploadedFile::fake()->image('tanda.png', 20, 20)->store('signatures', 'public'),
        ]);

        // Data URI harus bisa dibaca DomPDF (gambar, bukan berkas hilang).
        $dataUri = $technician->signature_data_uri;
        $this->assertIsString($dataUri);
        $this->assertStringStartsWith('data:image/', $dataUri);
        $this->assertNotFalse(base64_decode(substr($dataUri, strpos($dataUri, ',') + 1), true));

        $item = Item::create([
            'name' => 'Kabel HDMI',
            'item_code' => 'BRG-001',
            'category' => 'Peralatan',
            'stock' => 5,
        ]);
        Loan::create([
            'uuid' => (string) Str::uuid(),
            'loan_code' => 'PJM-2026-7001',
            'item_id' => $item->id,
            'qty' => 1,
            'borrower_name' => 'Budi Santoso',
            'borrower_email' => 'budi@example.com',
            'status' => 'borrowed',
            'created_by' => $admin->id,
        ]);
        Student::create([
            'student_id' => '2211082001',
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
        ]);

        // PDF sudah memuat logo kop surat, jadi yang dibandingkan adalah
        // JUMLAH objek gambar: memilih teknisi bertanda tangan harus menambah
        // satu gambar (yaitu tanda tangannya) pada Laporan dan Surat.
        $laporanTanpa = $this->countPdfImages($this->get('/api/loans/report/download')->assertOk()->getContent());
        $laporanDengan = $this->countPdfImages(
            $this->get('/api/loans/report/download?technician_id='.$technician->id)->assertOk()->getContent(),
        );
        $this->assertSame(
            $laporanTanpa + 1,
            $laporanDengan,
            'Laporan Peminjaman memuat satu gambar tambahan berupa tanda tangan digital',
        );

        $suratTanpa = $this->countPdfImages($this->post('/api/loans/clearance/download', [
            'student_id' => '2211082001',
            'purpose' => 'Persyaratan wisuda',
            'signatory_name' => 'Petugas Memduh',
            'signatory_nip' => '2002',
        ])->assertOk()->getContent());

        $suratDengan = $this->countPdfImages($this->post('/api/loans/clearance/download', [
            'student_id' => '2211082001',
            'purpose' => 'Persyaratan wisuda',
            'signatory_name' => $technician->name,
            'signatory_nip' => $technician->nip,
            'signatory_technician_id' => $technician->id,
        ])->assertOk()->getContent());

        $this->assertSame(
            $suratTanpa + 1,
            $suratDengan,
            'Surat Bebas Labor memuat satu gambar tambahan berupa tanda tangan digital',
        );

        // Tanpa tanda tangan, technician tetap sah sebagai penandatangan teks.
        $tanpaTanda = Technician::create(['name' => 'Tanpa Tanda Tangan', 'nip' => '2003']);
        $suratTeknisiKosong = $this->countPdfImages($this->post('/api/loans/clearance/download', [
            'student_id' => '2211082001',
            'purpose' => 'Persyaratan wisuda',
            'signatory_name' => $tanpaTanda->name,
            'signatory_nip' => $tanpaTanda->nip,
            'signatory_technician_id' => $tanpaTanda->id,
        ])->assertOk()->getContent());
        $this->assertSame($suratTanpa, $suratTeknisiKosong, 'Technisi tanpa tanda tangan tidak menambah gambar');
    }

    public function test_tanda_tangan_petugas_masuk_ke_surat_peminjaman_resmi(): void
    {
        Storage::fake('public');
        $this->admin();

        $technician = Technician::create([
            'name' => 'Petugas Peminjaman',
            'nip' => '4001',
            'signature_path' => UploadedFile::fake()->image('tt.png', 20, 20)->store('signatures', 'public'),
        ]);

        $item = Item::create([
            'name' => 'Kabel HDMI',
            'item_code' => 'BRG-001',
            'category' => 'Peralatan',
            'stock' => 5,
        ]);

        $payload = [
            'items' => [['item_id' => $item->id, 'qty' => 1]],
            'borrower_name' => 'Instansi Mitra',
            'borrower_email' => 'pinjam@example.com',
            'purpose' => 'Kegiatan Praktikum',
            'borrowed_date' => now()->toDateString(),
            'return_date' => now()->addWeek()->toDateString(),
            'signatory_name' => 'Penanggung Jawab',
            'signatory_nip' => '5001',
            'officer_name' => $technician->name,
            'officer_nip' => $technician->nip,
        ];

        $tanpaTanda = $this->postJson('/api/loans/official/download', $payload)->assertOk()->getContent();
        $denganTanda = $this->postJson('/api/loans/official/download', $payload + [
            'officer_technician_id' => $technician->id,
        ])->assertOk()->getContent();

        $this->assertSame(
            $this->countPdfImages($tanpaTanda) + 1,
            $this->countPdfImages($denganTanda),
            'Surat Peminjaman Resmi memuat tanda tangan digital petugas',
        );
    }

    public function test_tanda_tangan_svg_ditolak_dan_svg_lama_tidak_merusak_pdf(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->withHeader('Accept', 'application/json')->post('/api/technicians', [
            'name' => 'Unggahan SVG',
            'nip' => '3002',
            'signature' => UploadedFile::fake()->createWithContent(
                'tt.svg',
                '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="40"></svg>',
            ),
        ])->assertUnprocessable()->assertJsonValidationErrors('signature');

        // Data lama yang sudah tersimpan sebagai SVG harus diabaikan oleh
        // DomPDF agar tidak membuat dokumen gagal dirender.
        $technician = Technician::create([
            'name' => 'Teknisi SVG',
            'nip' => '3001',
            'signature_path' => UploadedFile::fake()->createWithContent('tt.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="40"><path d="M5 30 C 20 5, 40 35, 60 15 S 100 30, 115 10" stroke="black" fill="none" stroke-width="2"/></svg>')
                ->store('signatures', 'public'),
        ]);

        $image = Item::create([
            'name' => 'Kabel HDMI',
            'item_code' => 'BRG-001',
            'category' => 'Peralatan',
            'stock' => 5,
        ]);
        Loan::create([
            'uuid' => (string) Str::uuid(),
            'loan_code' => 'PJM-2026-7002',
            'item_id' => $image->id,
            'qty' => 1,
            'borrower_name' => 'Budi Santoso',
            'borrower_email' => 'budi@example.com',
            'status' => 'borrowed',
            'created_by' => $admin->id,
        ]);

        $pdfWithoutSignature = $this->get('/api/loans/report/download')->assertOk()->getContent();
        $pdfWithLegacySvg = $this->get('/api/loans/report/download?technician_id='.$technician->id)->assertOk()->getContent();

        $this->assertStringStartsWith('%PDF', $pdfWithLegacySvg);
        $this->assertSame(
            $this->countPdfImages($pdfWithoutSignature),
            $this->countPdfImages($pdfWithLegacySvg),
            'Tanda tangan SVG lama tidak disematkan ke PDF',
        );
    }

    /** Jumlah objek gambar (/Subtype /Image) di dalam berkas PDF. */
    private function countPdfImages(string $pdf): int
    {
        return substr_count($pdf, '/Subtype /Image');
    }

    public function test_cetak_laporan_menghasilkan_dokumen_berdiri_sendiri(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $item = Item::create([
            'name' => 'Kabel HDMI', 'item_code' => 'BRG-001', 'category' => 'Peralatan', 'stock' => 5,
        ]);
        Loan::create([
            'uuid' => (string) Str::uuid(),
            'loan_code' => 'PJM-2026-8001',
            'item_id' => $item->id,
            'qty' => 2,
            'borrower_name' => 'Budi Santoso',
            'borrower_email' => 'budi@example.com',
            'status' => 'borrowed',
            'created_by' => $admin->id,
        ]);

        $technician = Technician::create([
            'name' => 'Nofa Hendrayana, S.T.',
            'nip' => '198501012010011001',
            'signature_path' => UploadedFile::fake()->image('tanda.png', 40, 20)->store('signatures', 'public'),
        ]);

        $response = $this->get('/api/loans/report/print?technician_id='.$technician->id);

        $response->assertOk();
        $html = $response->getContent();

        // Dokumen berdiri sendiri: memuat kop surat, tabel, dan tanda tangan
        // digital, tanpa kerangka aplikasi apa pun.
        $this->assertStringContainsString('LAPORAN PEMINJAMAN BARANG', $html);
        $this->assertStringContainsString('POLITEKNIK NEGERI PADANG', $html);
        $this->assertStringContainsString('PJM-2026-8001', $html);
        $this->assertStringContainsString('Budi Santoso', $html);
        $this->assertStringContainsString('Nofa Hendrayana, S.T.', $html);
        $this->assertStringContainsString('signature-image', $html);
        $this->assertStringContainsString('data:image/', $html);

        // Kerangka aplikasi tidak boleh ikut: itulah sebab hasil cetak sebelumnya
        // kosong/tergeser karena sidebar & footer tetap serta offset `md:pl-*`.
        $this->assertStringNotContainsString('app-shell', $html);
        $this->assertStringNotContainsString('app-content', $html);
        $this->assertStringNotContainsString('developed by Muhammad Syaiful', $html);
    }

    public function test_cetak_laporan_tanpa_tanda_tangan_tetap_berisi_dokumen(): void
    {
        $this->admin();

        $response = $this->get('/api/loans/report/print');

        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('LAPORAN PEMINJAMAN BARANG', $html);
        // Tanpa transaksi tetap terbit dengan keterangan kosong, bukan halaman blank.
        $this->assertStringContainsString('Tidak ada transaksi pada filter yang dipilih.', $html);
        // Tanpa teknisi bertanda tangan, tidak ada elemen gambar tanda tangan.
        $this->assertStringNotContainsString('<img class="signature-image"', $html);
        $this->assertStringContainsString('____________________________', $html);
    }

    public function test_cetak_laporan_hanya_untuk_pengguna_terautentikasi(): void
    {
        // Memakai Accept JSON agar permintaan tanpa sesi dijawab 401, bukan
        // dialihkan ke halaman login yang tidak dipakai API ini.
        $this->getJson('/api/loans/report/print')->assertStatus(401);
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

