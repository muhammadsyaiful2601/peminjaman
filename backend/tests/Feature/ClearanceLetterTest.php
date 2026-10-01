<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Loan;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

/**
 * Surat Keterangan Bebas Laboratorium.
 *
 * Aturan bisnis:
 * 1. Daftar pada halaman Bebas Labor memakai data mahasiswa (sama dengan halaman
 *    Data Mahasiswa).
 * 2. Surat dapat diterbitkan untuk setiap peminjam yang tidak memiliki
 *    tanggungan: seluruh barang sudah dikembalikan, termasuk mahasiswa yang
 *    belum pernah meminjam barang sama sekali (dan yang transaksinya `rejected`).
 *    Untuk peminjam tanpa riwayat, surat menyatakan tidak ada transaksi
 *    peminjaman yang tercatat.
 * 3. Bila seluruh barang sudah dikembalikan, surat berupa Surat Keterangan Bebas
 *    Laboratorium; bila masih ada `borrowed`/`pending`, surat otomatis berupa
 *    Surat Keterangan Tanggungan. Surat bebas labor selalu menyatakan "tidak ada
 *    tanggungan" dan tidak pernah memuat paragraf/tabel tanggungan (tabel rincian
 *    menuliskan "Tidak ada tanggungan peminjaman barang yang tercatat" bila
 *    peminjam belum pernah meminjam). Keperluan surat tampil sebagai baris pada
 *    tabel identitas surat, dan surat tidak memuat jejak waktu cetak
 *    ("Dicetak dari sistem pada ... WIB") agar tampilannya tetap resmi.
 * 4. Transaksi lama (dibuat sebelum data mahasiswa tersedia) tetap dihubungkan ke
 *    mahasiswa bila salah satu data cocok: NIM, email, nama, atau nomor telepon.
 *    Peminjam manual yang tidak cocok dengan data mahasiswa mana pun tetap tampil
 *    (dari data peminjaman) agar tanggungannya terpantau.
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
     * Data mahasiswa (halaman Data Mahasiswa) yang dipakai halaman Bebas Labor.
     */
    private function student(string $studentId = self::NIM, string $name = 'Budi Santoso', string $email = self::EMAIL): Student
    {
        return Student::create([
            'student_id' => $studentId,
            'name' => $name,
            'email' => $email,
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

    public function test_daftar_peminjam_manual_ditandai_masih_punya_tanggungan(): void
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

        // Peminjam ini tidak punya data mahasiswa, jadi tampil dari data peminjaman.
        $response->assertStatus(200)
            ->assertJsonPath('data.0.name', 'Budi Santoso')
            ->assertJsonPath('data.0.student_id', self::NIM)
            ->assertJsonPath('data.0.has_student_record', false)
            ->assertJsonPath('data.0.has_loans', true)
            ->assertJsonPath('data.0.total_loans', 2)
            ->assertJsonPath('data.0.returned_loans', 1)
            ->assertJsonPath('data.0.outstanding_loans', 1)
            ->assertJsonPath('data.0.total_qty', 3)
            ->assertJsonPath('data.0.outstanding_qty', 1)
            ->assertJsonPath('data.0.eligible', false)
            ->assertJsonPath('data.0.letter_status', 'tanggungan')
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
        // Surat tidak memuat jejak waktu cetak agar tetap terlihat resmi.
        $this->assertStringNotContainsString('Dicetak dari sistem', $text);
        $this->assertStringNotContainsString('WIB', $text);
    }

    /**
     * Nomor surat bulan berjalan: `001/BEBAS-LAB/PNP/{bulan romawi}/{tahun}`.
     *
     * Dihitung dari tanggal sistem, bukan ditulis tetap, supaya pengujian tidak
     * gagal hanya karena bulan berganti.
     */
    private function currentLetterNumber(): string
    {
        $romans = [1 => 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
        $today = now();

        return sprintf('001/BEBAS-LAB/PNP/%s/%s', $romans[(int) $today->month], $today->year);
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
        $response->assertHeader('X-Clearance-Letter-Number', $this->currentLetterNumber());
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
        $this->assertStringContainsString('Tidak ada tanggungan (bebas labor)', $text);
        $this->assertStringContainsString('Budi Santoso', $text);
        // Keperluan surat tampil pada tabel identitas.
        $this->assertStringContainsString('Keperluan', $text);
        $this->assertStringContainsString('Persyaratan pengambilan bebas pustaka', $text);
        // Surat tidak memuat jejak waktu cetak agar tetap terlihat resmi.
        $this->assertStringNotContainsString('Dicetak dari sistem', $text);
        $this->assertStringNotContainsString('WIB', $text);
        $this->assertStringContainsString($loan->loan_code, $text);
        $this->assertStringContainsString($this->currentLetterNumber(), $text);
        $this->assertStringContainsString('Nofa Hendrayana', $text);
        // Surat bebas labor tidak boleh memuat pernyataan tanggungan.
        $this->assertStringNotContainsString('masih memiliki tanggungan', $text);

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

    public function test_daftar_bebas_labor_memakai_data_mahasiswa_dan_menandai_kelayakan(): void
    {
        Sanctum::actingAs($this->staff());
        $staff = $this->staff();

        $this->student();                                                // sudah meminjam & semua kembali
        $this->student('2211082002', 'Siti Aminah', 'siti@example.com');  // masih menahan barang
        $this->student('2211082003', 'Andi Pratama', 'andi@example.com'); // belum pernah meminjam

        $this->makeLoan($staff);
        $this->makeLoan($staff, [
            'borrower_name' => 'Siti Aminah',
            'borrower_email' => 'siti@example.com',
            'borrower_student_id' => '2211082002',
            'status' => 'borrowed',
            'returned_at' => null,
            'borrowed_at' => now(),
        ]);

        $response = $this->getJson('/api/loans/clearance/borrowers')->assertStatus(200);

        // Seluruh mahasiswa tampil, termasuk yang belum pernah meminjam.
        $this->assertSame(3, $response->json('meta.total'));

        $rows = collect($response->json('data'))->keyBy('student_id');

        // Semua barang sudah kembali -> surat bebas labor.
        $this->assertTrue($rows[self::NIM]['has_student_record']);
        $this->assertTrue($rows[self::NIM]['has_loans']);
        $this->assertTrue($rows[self::NIM]['eligible']);
        $this->assertTrue($rows[self::NIM]['can_issue_letter']);
        $this->assertSame('bebas_labor', $rows[self::NIM]['letter_status']);
        $this->assertSame(1, $rows[self::NIM]['total_loans']);

        // Masih ada barang yang belum kembali -> hanya surat tanggungan.
        $this->assertTrue($rows['2211082002']['has_loans']);
        $this->assertFalse($rows['2211082002']['eligible']);
        $this->assertSame('tanggungan', $rows['2211082002']['letter_status']);
        $this->assertSame(1, $rows['2211082002']['outstanding_loans']);
        $this->assertSame('siti@example.com', $rows['2211082002']['email']);

        // Belum pernah meminjam -> tidak ada tanggungan, tetap bebas labor dan
        // suratnya dapat diterbitkan dengan keterangan tanpa riwayat.
        $this->assertFalse($rows['2211082003']['has_loans']);
        $this->assertTrue($rows['2211082003']['eligible']);
        $this->assertTrue($rows['2211082003']['can_issue_letter']);
        $this->assertSame('belum_pernah_meminjam', $rows['2211082003']['letter_status']);
        $this->assertSame(0, $rows['2211082003']['total_loans']);
    }

    public function test_pencarian_daftar_bebas_labor_memakai_data_mahasiswa(): void
    {
        Sanctum::actingAs($this->staff());
        $staff = $this->staff();

        $this->student();
        $this->student('2211082002', 'Siti Aminah', 'siti@example.com');

        $this->makeLoan($staff);
        $this->makeLoan($staff, [
            'borrower_name' => 'Siti Aminah',
            'borrower_email' => 'siti@example.com',
            'borrower_student_id' => '2211082002',
            'status' => 'borrowed',
            'returned_at' => null,
            'borrowed_at' => now(),
        ]);

        $this->getJson('/api/loans/clearance/borrowers?search=2211082002')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Siti Aminah');

        $this->getJson('/api/loans/clearance/borrowers?search=Santoso')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.student_id', self::NIM);

        $this->getJson('/api/loans/clearance/borrowers?search=siti@example.com')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.student_id', '2211082002');

        $this->getJson('/api/loans/clearance/borrowers?search=TidakAdaMahasiswa')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 0);
    }

    public function test_mahasiswa_tanpa_riwayat_peminjaman_tetap_mendapat_surat_bebas_labor(): void
    {
        Sanctum::actingAs($this->staff());

        $this->student('2211082099', 'Tanpa Riwayat', 'tanpa@example.com');

        // Tanpa transaksi berarti tidak ada tanggungan: mahasiswa tetap bebas
        // labor dan suratnya dapat diterbitkan dengan keterangan tanpa riwayat.
        $this->getJson('/api/loans/clearance/detail?student_id=2211082099&borrower_email=tanpa@example.com&borrower_name=Tanpa+Riwayat')
            ->assertStatus(200)
            ->assertJsonPath('borrower.name', 'Tanpa Riwayat')
            ->assertJsonPath('borrower.has_student_record', true)
            ->assertJsonPath('has_loans', false)
            ->assertJsonPath('can_issue_letter', true)
            ->assertJsonPath('eligible', true)
            ->assertJsonPath('letter_status', 'belum_pernah_meminjam')
            ->assertJsonPath('totals.total_loans', 0);

        $response = $this->postJson('/api/loans/clearance/download', [
            'student_id' => '2211082099',
            'purpose' => 'Persyaratan bebas pustaka',
            'signatory_name' => 'Nofa Hendrayana, S.T.',
            'signatory_nip' => '197907182025211025',
        ]);

        $response->assertStatus(200);
        $response->assertHeader('X-Clearance-Eligible', '1');
        $this->assertStringContainsString(
            'surat-bebas-labor-Tanpa-Riwayat',
            (string) $response->headers->get('content-disposition'),
        );

        // Surat menyatakan bebas labor sekaligus menegaskan tidak ada transaksi
        // dan tidak ada tanggungan (tanpa pernyataan tanggungan palsu).
        $text = $this->pdfText($response->getContent());
        $this->assertStringContainsString('SURAT KETERANGAN BEBAS LABORATORIUM', $text);
        $this->assertStringContainsString('Tidak ada transaksi peminjaman', $text);
        $this->assertStringContainsString('Tidak ada tanggungan (bebas labor)', $text);
        $this->assertStringContainsString('Tidak ada tanggungan peminjaman barang', $text);
        $this->assertStringContainsString('Keperluan', $text);
        $this->assertStringContainsString('Persyaratan bebas pustaka', $text);
        $this->assertStringNotContainsString('masih memiliki tanggungan', $text);
        $this->assertStringNotContainsString('SURAT KETERANGAN TANGGUNGAN PEMINJAMAN LABORATORIUM', $text);

        $this->assertDatabaseCount('clearance_letters', 1);
    }

    public function test_transaksi_ditolak_menghasilkan_surat_bebas_labor_tanpa_riwayat(): void
    {
        Sanctum::actingAs($this->staff());
        $staff = $this->staff();

        $this->student();

        // Pengajuan yang ditolak: peminjam tidak pernah menerima barang, sehingga
        // tetap dianggap bebas labor (tanpa riwayat peminjaman).
        $this->makeLoan($staff, [
            'status' => 'rejected',
            'returned_at' => null,
            'borrowed_at' => null,
        ]);

        $this->getJson('/api/loans/clearance/borrowers?search=' . self::NIM)
            ->assertStatus(200)
            ->assertJsonPath('data.0.has_loans', false)
            ->assertJsonPath('data.0.eligible', true)
            ->assertJsonPath('data.0.can_issue_letter', true)
            ->assertJsonPath('data.0.letter_status', 'belum_pernah_meminjam');

        $this->postJson('/api/loans/clearance/download', [
            'student_id' => self::NIM,
            'purpose' => 'Persyaratan bebas pustaka',
            'signatory_name' => 'Nofa Hendrayana, S.T.',
            'signatory_nip' => '197907182025211025',
        ])->assertStatus(200)->assertHeader('X-Clearance-Eligible', '1');

        $this->assertDatabaseCount('clearance_letters', 1);
    }

    public function test_surat_memakai_identitas_data_mahasiswa(): void
    {
        Sanctum::actingAs($this->staff());
        $staff = $this->staff();

        // Nama pada data mahasiswa berbeda dari snapshot transaksi; surat harus
        // memakai data mahasiswa (sumber sama dengan halaman Data Mahasiswa).
        $this->student(self::NIM, 'Budi Santoso Muda', 'budi.mahasiswa@example.com');

        $loan = $this->makeLoan($staff);

        $response = $this->postJson('/api/loans/clearance/download', [
            'student_id' => self::NIM,
            'borrower_email' => 'budi.mahasiswa@example.com',
            'borrower_name' => 'Budi Santoso Muda',
            'purpose' => 'Persyaratan bebas pustaka',
            'signatory_name' => 'Nofa Hendrayana, S.T.',
            'signatory_nip' => '197907182025211025',
        ]);

        $response->assertStatus(200);
        $this->assertStringContainsString(
            'surat-bebas-labor-Budi-Santoso-Muda',
            (string) $response->headers->get('content-disposition'),
        );

        $text = $this->pdfText($response->getContent());
        $this->assertStringContainsString('Budi Santoso Muda', $text);
        $this->assertStringContainsString('budi.mahasiswa@example.com', $text);
        $this->assertStringContainsString($loan->loan_code, $text);
    }

    public function test_transaksi_lama_dicocokkan_ke_mahasiswa_walau_hanya_satu_data_yang_sama(): void
    {
        Sanctum::actingAs($this->staff());
        $staff = $this->staff();

        // Data mahasiswa baru tersedia setelah beberapa peminjaman terjadi.
        Student::create([
            'student_id' => self::NIM,
            'name' => 'Budi Santoso',
            'email' => self::EMAIL,
            'phone' => '081234567890',
        ]);

        // (1) Transaksi lama: hanya NAMA yang sama (tanpa NIM, email berbeda).
        $this->makeLoan($staff, [
            'borrower_name' => 'budi santoso',
            'borrower_email' => 'budi.lama@example.com',
            'borrower_student_id' => null,
            'status' => 'borrowed',
            'returned_at' => null,
            'borrowed_at' => now(),
        ]);

        // (2) Transaksi lama: hanya EMAIL yang sama (nama berbeda, tanpa NIM).
        $this->makeLoan($staff, [
            'borrower_name' => 'Budi S.',
            'borrower_email' => self::EMAIL,
            'borrower_student_id' => null,
        ]);

        // (3) Transaksi lama: hanya NOMOR TELEPON yang sama (+62 vs 0).
        $this->makeLoan($staff, [
            'borrower_name' => 'B S',
            'borrower_email' => 'lain@example.com',
            'borrower_student_id' => null,
            'borrower_phone' => '+6281234567890',
            'returned_at' => now()->subDay(),
        ]);

        $response = $this->getJson('/api/loans/clearance/borrowers?search=' . self::NIM)->assertStatus(200);

        // Ketiga transaksi tercocokkan ke mahasiswa tersebut; tidak ada baris
        // peminjam manual yang terpisah.
        $this->assertSame(1, $response->json('meta.total'));

        $row = $response->json('data.0');
        $this->assertTrue($row['has_student_record']);
        $this->assertSame(self::NIM, $row['student_id']);
        $this->assertSame(3, $row['total_loans']);
        $this->assertSame(1, $row['outstanding_loans']);
        $this->assertSame('tanggungan', $row['letter_status']);

        // Halaman detail memakai pencocokan yang sama.
        $this->getJson('/api/loans/clearance/detail?student_id=' . self::NIM . '&borrower_email=' . self::EMAIL)
            ->assertStatus(200)
            ->assertJsonPath('has_loans', true)
            ->assertJsonPath('totals.total_loans', 3)
            ->assertJsonPath('totals.outstanding_loans', 1);
    }

    public function test_nim_pada_transaksi_mengikat_bila_terdaftar_pada_mahasiswa_lain(): void
    {
        Sanctum::actingAs($this->staff());
        $staff = $this->staff();

        $this->student();
        $this->student('2211082002', 'Siti Aminah', 'siti@example.com');

        // Transaksi memakai NIM Siti, tetapi nama & email Budi.
        $this->makeLoan($staff, [
            'borrower_name' => 'Budi Santoso',
            'borrower_email' => self::EMAIL,
            'borrower_student_id' => '2211082002',
            'status' => 'borrowed',
            'returned_at' => null,
            'borrowed_at' => now(),
        ]);

        $rows = collect($this->getJson('/api/loans/clearance/borrowers')->assertStatus(200)->json('data'))
            ->keyBy('student_id');

        // Pemilik NIM yang dipakai, bukan pemilik nama/email.
        $this->assertSame(0, $rows[self::NIM]['total_loans']);
        $this->assertFalse($rows[self::NIM]['has_loans']);
        $this->assertSame(1, $rows['2211082002']['total_loans']);
        $this->assertSame(1, $rows['2211082002']['outstanding_loans']);
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

    public function test_cetak_masal_menghasilkan_dokumen_html_untuk_mahasiswa_bebas_labor(): void
    {
        Sanctum::actingAs($this->staff());
        $staff = $this->staff();

        $this->student();
        $this->student('2211082002', 'Siti Aminah', 'siti@example.com');

        // Budi dan Siti sama-sama pernah meminjam dan sudah mengembalikan
        $this->makeLoan($staff, ['returned_at' => now(), 'status' => 'returned']);
        $this->makeLoan($staff, [
            'borrower_student_id' => '2211082002',
            'borrower_name' => 'Siti Aminah',
            'borrower_email' => 'siti@example.com',
            'returned_at' => now(),
            'status' => 'returned',
        ]);

        $response = $this->postJson('/api/loans/clearance/print', [
            'borrowers' => [
                ['student_id' => self::NIM, 'borrower_name' => 'Budi Santoso', 'borrower_email' => self::EMAIL],
                ['student_id' => '2211082002', 'borrower_name' => 'Siti Aminah', 'borrower_email' => 'siti@example.com'],
            ],
            'purpose' => 'Persyaratan yudisium',
            'signatory_name' => 'Nofa Hendrayana, S.T.',
            'signatory_nip' => '197907182025211025',
        ]);

        $response->assertStatus(200);
        $this->assertStringContainsString('SURAT KETERANGAN BEBAS LABORATORIUM', $response->getContent());
        $this->assertStringContainsString('Budi Santoso', $response->getContent());
        $this->assertStringContainsString('Siti Aminah', $response->getContent());
        // Dua surat bebas labor: masing-masing menyatakan tidak ada tanggungan.
        $this->assertSame(2, substr_count($response->getContent(), 'Tidak ada tanggungan (bebas labor)'));
        $this->assertStringContainsString('Tanggal kembali', $response->getContent());
        $this->assertStringNotContainsString('masih memiliki tanggungan', $response->getContent());
        // Tidak ada jejak waktu cetak pada dokumen surat.
        $this->assertStringNotContainsString('Dicetak dari sistem', $response->getContent());
        $this->assertStringNotContainsString('WIB', $response->getContent());
    }

    public function test_cetak_masal_menolak_mahasiswa_yang_belum_bebas_labor(): void
    {
        Sanctum::actingAs($this->staff());
        $staff = $this->staff();

        $this->student();
        $this->student('2211082002', 'Siti Aminah', 'siti@example.com');

        // Budi sudah kembali, Siti masih pinjam
        $this->makeLoan($staff, ['returned_at' => now(), 'status' => 'returned']);
        $this->makeLoan($staff, [
            'borrower_student_id' => '2211082002',
            'borrower_name' => 'Siti Aminah',
            'borrower_email' => 'siti@example.com',
            'status' => 'borrowed',
            'returned_at' => null,
            'borrowed_at' => now(),
        ]);

        $response = $this->postJson('/api/loans/clearance/print', [
            'borrowers' => [
                ['student_id' => self::NIM],
                ['student_id' => '2211082002'],
            ],
            'purpose' => 'Persyaratan yudisium',
            'signatory_name' => 'Nofa Hendrayana, S.T.',
            'signatory_nip' => '197907182025211025',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['borrowers']);
    }

    public function test_cetak_masal_menerima_mahasiswa_yang_belum_pernah_meminjam(): void
    {
        Sanctum::actingAs($this->staff());
        $this->student();

        $response = $this->postJson('/api/loans/clearance/print', [
            'borrowers' => [
                ['student_id' => self::NIM],
            ],
            'purpose' => 'Persyaratan yudisium',
            'signatory_name' => 'Nofa Hendrayana, S.T.',
            'signatory_nip' => '197907182025211025',
        ]);

        // Tanpa transaksi berarti bebas labor: cetak masal tetap menghasilkan
        // surat bebas labor dengan keterangan tidak ada transaksi peminjaman.
        $response->assertStatus(200);
        $html = $response->getContent();
        $this->assertStringContainsString('SURAT KETERANGAN BEBAS LABORATORIUM', $html);
        $this->assertStringContainsString('Budi Santoso', $html);
        $this->assertStringContainsString('Tidak ada transaksi peminjaman', $html);
        $this->assertStringContainsString('belum pernah melakukan peminjaman barang', $html);

        // Tabel rincian menuliskan tidak ada tanggungan, dan surat tidak memuat
        // pernyataan tanggungan (0 transaksi/0 unit) yang menyesatkan.
        $this->assertStringContainsString('Tidak ada tanggungan (bebas labor)', $html);
        $this->assertStringContainsString('Tidak ada tanggungan peminjaman barang yang tercatat.', $html);
        $this->assertStringNotContainsString('masih memiliki tanggungan', $html);
        $this->assertStringNotContainsString('0 transaksi (0 unit barang)', $html);

        // Keperluan surat tampil sebagai baris pada tabel identitas.
        $this->assertStringContainsString('<td>Keperluan</td>', $html);
        $this->assertStringContainsString('<td>Persyaratan yudisium</td>', $html);
    }

    public function test_surat_bebas_labor_menyatakan_tidak_ada_tanggungan(): void
    {
        Sanctum::actingAs($this->staff());
        $staff = $this->staff();

        // Sudah mengembalikan seluruh barang -> bebas labor.
        $this->makeLoan($staff, ['status' => 'returned', 'returned_at' => now()->subDay()]);

        $response = $this->postJson('/api/loans/clearance/print', [
            'borrowers' => [
                ['student_id' => self::NIM],
            ],
            'purpose' => 'Persyaratan yudisium',
            'signatory_name' => 'Nofa Hendrayana, S.T.',
            'signatory_nip' => '197907182025211025',
        ]);

        $html = $response->getContent();
        $response->assertStatus(200);
        $this->assertStringContainsString('tidak memiliki tanggungan peminjaman barang', $html);
        $this->assertStringContainsString('Tidak ada tanggungan (bebas labor)', $html);
        $this->assertStringContainsString('Tanggal kembali', $html);
        $this->assertStringContainsString('<td>Keperluan</td>', $html);
        $this->assertStringContainsString('<td>Persyaratan yudisium</td>', $html);
        $this->assertStringNotContainsString('masih memiliki tanggungan', $html);
        $this->assertStringNotContainsString('belum dikembalikan, dengan rincian', $html);
        $this->assertStringNotContainsString('Dicetak dari sistem', $html);
        $this->assertStringNotContainsString('WIB', $html);
    }

}
