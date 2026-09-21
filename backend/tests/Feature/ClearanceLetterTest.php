<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

/**
 * Surat Keterangan Bebas Laboratorium.
 *
 * Aturan bisnis: surat hanya dapat diterbitkan bila peminjam tidak memiliki
 * transaksi yang belum dikembalikan (`borrowed`/`pending`). Isi surat diambil
 * dari data peminjaman peminjam tersebut.
 */
class ClearanceLetterTest extends TestCase
{
    use RefreshDatabase;

    private const NIM = '2211082001';

    private const EMAIL = 'budi@example.com';

    private function staff(string $role = 'admin'): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function item(): Item
    {
        return Item::create([
            'item_code' => 'ELE-001',
            'name' => 'Arduino Uno Kit',
            'category' => 'Elektronik',
            'stock' => 10,
        ]);
    }

    /**
     * @param  array<int, array{item_id: int, qty: int}>|null  $items
     */
    private function makeLoan(User $staff, array $attributes = [], ?array $items = null): Loan
    {
        $item = $this->itemOrCreate();

        $loan = Loan::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'qr_token' => (string) Str::uuid(),
            'loan_code' => 'PJM-2026-' . str_pad((string) (Loan::count() + 1), 4, '0', STR_PAD_LEFT),
            'item_id' => $item->id,
            'qty' => 1,
            'borrower_name' => 'Budi Santoso',
            'borrower_email' => self::EMAIL,
            'borrower_student_id' => self::NIM,
            'status' => 'returned',
            'borrowed_at' => now()->subDays(10),
            'returned_at' => now()->subDays(2),
            'condition_on_return' => 'bagus',
            'created_by' => $staff->id,
            'verified_by' => $staff->id,
        ], $attributes));

        $loan->loanItems()->createMany($items ?? [['item_id' => $item->id, 'qty' => $loan->qty]]);

        return $loan->refresh();
    }

    private function itemOrCreate(): Item
    {
        return Item::query()->first() ?? $this->item();
    }

    /**
     * Teks isi PDF (spasi dinormalkan agar mudah diperiksa).
     */
    private function pdfText(string $content): string
    {
        $text = (new Parser())->parseContent($content)->getText();

        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    public function test_daftar_peminjam_menandai_yang_masih_punya_tanggungan(): void
    {
        Sanctum::actingAs($this->staff());

        $staff = $this->staff();

        $this->makeLoan($staff, ['returned_at' => now()->subDay()], [['item_id' => $this->itemOrCreate()->id, 'qty' => 2]]);
        $this->makeLoan($staff, [
            'status' => 'borrowed',
            'returned_at' => null,
            'borrowed_at' => now(),
        ], [['item_id' => $this->itemOrCreate()->id, 'qty' => 1]]);

        $response = $this->getJson('/api/loans/clearance/borrowers?search=' . self::NIM);

        $response->assertStatus(200)
            ->assertJsonPath('data.0.name', 'Budi Santoso')
            ->assertJsonPath('data.0.student_id', self::NIM)
            ->assertJsonPath('data.0.total_loans', 2)
            ->assertJsonPath('data.0.returned_loans', 1)
            ->assertJsonPath('data.0.outstanding_loans', 1)
            ->assertJsonPath('data.0.total_qty', 3)
            ->assertJsonPath('data.0.outstanding_qty', 1)
            ->assertJsonPath('data.0.eligible', false)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_pencarian_peminjam_dapat_memakai_nama_dan_email(): void
    {
        Sanctum::actingAs($this->staff());

        $staff = $this->staff();
        $this->makeLoan($staff);

        $this->getJson('/api/loans/clearance/borrowers?search=Santoso')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.eligible', true);

        $this->getJson('/api/loans/clearance/borrowers?search=' . self::EMAIL)
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1);

        $this->getJson('/api/loans/clearance/borrowers?search=TidakAdaOrang')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 0);
    }

    public function test_detail_menampilkan_barang_belum_kembali_dan_riwayat(): void
    {
        Sanctum::actingAs($this->staff());

        $staff = $this->staff();
        $returned = $this->makeLoan($staff, ['returned_at' => now()->subDay()]);
        $borrowed = $this->makeLoan($staff, [
            'status' => 'borrowed',
            'returned_at' => null,
            'borrowed_at' => now(),
        ]);

        $response = $this->getJson('/api/loans/clearance/detail?student_id=' . self::NIM . '&borrower_email=' . self::EMAIL);

        $response->assertStatus(200)
            ->assertJsonPath('borrower.name', 'Budi Santoso')
            ->assertJsonPath('borrower.student_id', self::NIM)
            ->assertJsonPath('eligible', false)
            ->assertJsonPath('totals.total_loans', 2)
            ->assertJsonPath('totals.outstanding_loans', 1)
            ->assertJsonPath('totals.returned_loans', 1)
            ->assertJsonPath('outstanding.0.loan_code', $borrowed->loan_code)
            ->assertJsonPath('outstanding.0.status_label', 'Dipinjam')
            ->assertJsonPath('outstanding.0.items.0.name', 'Arduino Uno Kit')
            ->assertJsonPath('history.0.loan_code', $returned->loan_code)
            ->assertJsonPath('history.0.items.0.name', 'Arduino Uno Kit');
    }

    public function test_detail_peminjam_tak_dikenal_mengembalikan_404(): void
    {
        Sanctum::actingAs($this->staff());

        $this->getJson('/api/loans/clearance/detail?student_id=99999999')
            ->assertStatus(404)
            ->assertJsonPath('message', 'Data peminjaman peminjam tidak ditemukan.');
    }

    public function test_surat_selalu_dapat_diunduh_dan_memuat_tanggungan_saat_barang_belum_kembali(): void
    {
        Sanctum::actingAs($this->staff());

        $staff = $this->staff();
        $this->makeLoan($staff, ['returned_at' => now()->subDay()]);
        $borrowed = $this->makeLoan($staff, [
            'status' => 'borrowed',
            'returned_at' => null,
            'borrowed_at' => now(),
        ]);

        $response = $this->postJson('/api/loans/clearance/download', [
            'student_id' => self::NIM,
            'borrower_email' => self::EMAIL,
            'purpose' => 'Persyaratan pengambilan bebas pustaka',
            'signatory_name' => 'Nofa Hendrayana, S.T.',
            'signatory_nip' => '197907182025211025',
        ]);

        // Petugas selalu dapat mengunduh surat (tidak lagi diblokir 422).
        $response->assertStatus(200);
        $this->assertSame('0', $response->headers->get('X-Clearance-Eligible'));
        $this->assertStringContainsString('surat-tanggungan-labor-Budi-Santoso', (string) $response->headers->get('content-disposition'));

        $content = $response->getContent();
        $this->assertStringStartsWith('%PDF', $content);

        // Isi surat otomatis menjadi Surat Keterangan Tanggungan, bukan bebas labor.
        $text = $this->pdfText($content);
        $this->assertStringContainsString('SURAT KETERANGAN TANGGUNGAN PEMINJAMAN LABORATORIUM', $text);
        $this->assertStringContainsString('belum dikembalikan', $text);
        $this->assertStringContainsString($borrowed->loan_code, $text);
        $this->assertStringContainsString('Arduino Uno Kit', $text);
        $this->assertStringNotContainsString('SURAT KETERANGAN BEBAS LABORATORIUM', $text);
    }

    public function test_unduh_surat_bebas_labor_menghasilkan_pdf_berkop_surat(): void
    {
        Sanctum::actingAs($this->staff());

        $staff = $this->staff();
        $loan = $this->makeLoan($staff, ['returned_at' => now()->subDays(3)], [
            ['item_id' => $this->itemOrCreate()->id, 'qty' => 2],
        ]);

        $response = $this->postJson('/api/loans/clearance/download', [
            'student_id' => self::NIM,
            'borrower_email' => self::EMAIL,
            'purpose' => 'Persyaratan pengambilan bebas pustaka',
            'laboratory' => 'Laboratorium Komputer',
            'signatory_name' => 'Nofa Hendrayana, S.T.',
            'signatory_nip' => '197907182025211025',
        ]);

        $response->assertStatus(200);
        $response->assertHeader('X-Clearance-Eligible', '1');
        $response->assertHeader('X-Clearance-Letter-Number', '001/BEBAS-LAB/PNP/IX/2026');
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('surat-bebas-labor-Budi-Santoso', (string) $response->headers->get('content-disposition'));

        $content = $response->getContent();
        $this->assertStringStartsWith('%PDF', $content);
        $this->assertGreaterThan(1000, strlen($content));

        // Isi surat memuat kop surat dan pernyataan bebas tanggungan.
        // (Catatan: ekstraksi teks paragraf rata kanan-kiri tidak selalu lengkap,
        // jadi pemeriksaan diarahkan ke judul, tabel, dan identitas.)
        $text = $this->pdfText($content);
        $this->assertStringContainsString('SURAT KETERANGAN BEBAS LABORATORIUM', $text);
        $this->assertStringContainsString('POLITEKNIK NEGERI PADANG', $text);
        $this->assertStringContainsString('Tidak ada (bebas labor)', $text);
        $this->assertStringContainsString('Budi Santoso', $text);
        $this->assertStringContainsString($loan->loan_code, $text);
        $this->assertStringContainsString('001/BEBAS-LAB/PNP/IX/2026', $text);
        $this->assertStringContainsString('Nofa Hendrayana', $text);

        $this->assertNotNull($loan->returned_at);
    }

    public function test_unduh_surat_mewajibkan_keperluan_dan_penandatangan(): void
    {
        Sanctum::actingAs($this->staff());

        $this->makeLoan($this->staff());

        $this->postJson('/api/loans/clearance/download', [
            'student_id' => self::NIM,
        ])->assertStatus(422)->assertJsonValidationErrors(['purpose', 'signatory_name', 'signatory_nip']);

        $this->postJson('/api/loans/clearance/download', [])->assertStatus(422);
    }

    public function test_peminjam_tanpa_nim_dipisahkan_berdasarkan_nama(): void
    {
        Sanctum::actingAs($this->staff());
        $staff = $this->staff();

        // Email yang sama dipakai dua orang dan NIM keduanya kosong (data nyata
        // petugas yang hanya mengisi nama + email).
        $this->makeLoan($staff, [
            'borrower_name' => 'Testing Satu',
            'borrower_email' => 'shared@example.com',
            'borrower_student_id' => null,
        ]);

        $this->makeLoan($staff, [
            'borrower_name' => 'Testing Dua',
            'borrower_email' => 'shared@example.com',
            'borrower_student_id' => null,
            'status' => 'borrowed',
            'returned_at' => null,
            'borrowed_at' => now(),
        ]);

        $response = $this->getJson('/api/loans/clearance/borrowers?search=shared@example.com')->assertStatus(200);
        $rows = collect($response->json('data'));

        $this->assertCount(2, $rows, 'Dua peminjam dengan email sama harus tampil terpisah.');
        $this->assertSame(1, $rows->firstWhere('eligible', true)['total_loans']);
        $this->assertSame(1, $rows->firstWhere('eligible', false)['outstanding_loans']);

        // Nama tidak peka huruf besar/kecil -> tetap terhitung sebagai satu peminjam.
        $this->makeLoan($staff, [
            'borrower_name' => 'TESTING SATU',
            'borrower_email' => 'shared@example.com',
            'borrower_student_id' => null,
        ]);

        $response = $this->getJson('/api/loans/clearance/borrowers?search=shared@example.com')->assertStatus(200);
        $rows = collect($response->json('data'));

        $this->assertCount(2, $rows);
        $this->assertSame(2, $rows->firstWhere('eligible', true)['total_loans']);
    }

    public function test_unduh_surat_peminjam_tanpa_nim_hanya_memakai_transaksinya(): void
    {
        Sanctum::actingAs($this->staff());
        $staff = $this->staff();

        $this->makeLoan($staff, [
            'borrower_name' => 'Testing Satu',
            'borrower_email' => 'shared@example.com',
            'borrower_student_id' => null,
        ]);

        $this->makeLoan($staff, [
            'borrower_name' => 'Testing Dua',
            'borrower_email' => 'shared@example.com',
            'borrower_student_id' => null,
            'status' => 'borrowed',
            'returned_at' => null,
            'borrowed_at' => now(),
        ]);

        // Detail hanya memuat transaksi peminjam yang dipilih.
        $this->getJson('/api/loans/clearance/detail?borrower_email=shared@example.com&borrower_name=Testing+Satu')
            ->assertStatus(200)
            ->assertJsonPath('eligible', true)
            ->assertJsonPath('totals.total_loans', 1)
            ->assertJsonPath('totals.outstanding_loans', 0);

        $payload = [
            'borrower_email' => 'shared@example.com',
            'purpose' => 'Persyaratan bebas pustaka',
            'signatory_name' => 'Nofa Hendrayana, S.T.',
            'signatory_nip' => '197907182025211025',
        ];

        // Peminjam yang sudah mengembalikan semua barang tetap bisa mengunduh surat.
        $response = $this->postJson('/api/loans/clearance/download', $payload + ['borrower_name' => 'Testing Satu']);
        $response->assertStatus(200);
        $this->assertStringStartsWith('%PDF', $response->getContent());

        // Peminjam yang masih menahan barang tetap dapat diunduh, tetapi suratnya
        // berupa Surat Keterangan Tanggungan (bukan bebas labor).
        $blocked = $this->postJson('/api/loans/clearance/download', $payload + ['borrower_name' => 'Testing Dua']);
        $blocked->assertStatus(200);
        $this->assertSame('0', $blocked->headers->get('X-Clearance-Eligible'));
        $this->assertStringContainsString('surat-tanggungan-labor', (string) $blocked->headers->get('content-disposition'));
    }

    public function test_transaksi_lama_tanpa_nim_ikut_terhitung_bila_nama_dan_email_sama(): void
    {
        Sanctum::actingAs($this->staff());
        $staff = $this->staff();

        // Transaksi dengan NIM (sudah dikembalikan).
        $this->makeLoan($staff);

        // Transaksi lama tanpa NIM, nama + email sama -> harus ikut dihitung.
        $this->makeLoan($staff, [
            'borrower_student_id' => null,
            'status' => 'borrowed',
            'returned_at' => null,
            'borrowed_at' => now(),
        ]);

        $this->getJson('/api/loans/clearance/detail?student_id=' . self::NIM . '&borrower_email=' . self::EMAIL . '&borrower_name=Budi+Santoso')
            ->assertStatus(200)
            ->assertJsonPath('eligible', false)
            ->assertJsonPath('totals.total_loans', 2)
            ->assertJsonPath('totals.outstanding_loans', 1);
    }

    public function test_transaksi_peminjam_lain_dengan_email_sama_tidak_ikut_terhitung(): void
    {
        Sanctum::actingAs($this->staff());
        $staff = $this->staff();

        // Peminjam dengan NIM, seluruh barang sudah kembali.
        $this->makeLoan($staff);

        // Orang lain memakai email yang sama (NIM kosong) dan masih menahan barang.
        $this->makeLoan($staff, [
            'borrower_name' => 'Orang Lain',
            'borrower_student_id' => null,
            'status' => 'borrowed',
            'returned_at' => null,
            'borrowed_at' => now(),
        ]);

        $this->getJson('/api/loans/clearance/detail?student_id=' . self::NIM . '&borrower_email=' . self::EMAIL . '&borrower_name=Budi+Santoso')
            ->assertStatus(200)
            ->assertJsonPath('eligible', true)
            ->assertJsonPath('totals.total_loans', 1)
            ->assertJsonPath('totals.outstanding_loans', 0);
    }

    public function test_endpoint_bebas_labor_membutuhkan_login(): void
    {
        $this->getJson('/api/loans/clearance/borrowers')->assertStatus(401);
        $this->getJson('/api/loans/clearance/detail?student_id=' . self::NIM)->assertStatus(401);
        $this->postJson('/api/loans/clearance/download', ['student_id' => self::NIM])->assertStatus(401);
    }

    public function test_role_borrower_hanya_dapat_melihat_data(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'borrower']));
        $this->makeLoan($this->staff());

        // Melihat data peminjaman diizinkan untuk semua pengguna yang login.
        $this->getJson('/api/loans/clearance/detail?student_id=' . self::NIM)->assertStatus(200);

        // Menerbitkan surat hanya untuk petugas.
        $this->postJson('/api/loans/clearance/download', [
            'student_id' => self::NIM,
            'purpose' => 'Keperluan pribadi',
            'signatory_name' => 'Bukan Petugas',
            'signatory_nip' => '123456789',
        ])->assertStatus(403);
    }

    public function test_asisten_petugas_dapat_mengunduh_surat(): void
    {
        Sanctum::actingAs($this->staff('assistant'));
        $this->makeLoan($this->staff());

        $this->postJson('/api/loans/clearance/download', [
            'student_id' => self::NIM,
            'purpose' => 'Persyaratan yudisium',
            'signatory_name' => 'Nofa Hendrayana, S.T.',
            'signatory_nip' => '197907182025211025',
        ])->assertStatus(200);
    }
}
