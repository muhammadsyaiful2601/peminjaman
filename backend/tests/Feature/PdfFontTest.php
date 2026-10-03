<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Models\User;
use App\Support\Branding;
use App\Support\PdfFont;
use App\Support\QrPng;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Smalot\PdfParser\Font;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

/**
 * Font dokumen PDF.
 *
 * Dompdf tidak membaca font sistem operasi seperti browser. Tes ini memastikan
 * template memakai font bawaan yang tersedia, tanpa mengandalkan font eksternal.
 */
class PdfFontTest extends TestCase
{
    use RefreshDatabase;

    public function test_katalog_hanya_memuat_font_yang_bisa_dirender(): void
    {
        $keys = array_column(PdfFont::options(), 'key');

        foreach (['dejavu_sans', 'dejavu_serif', 'dejavu_sans_mono', 'times', 'helvetica', 'courier'] as $key) {
            $this->assertContains($key, $keys);
        }
        $this->assertNotContains('tahoma', $keys);

        // Nama font sistem akan diganti Dompdf diam-diam sehingga tidak
        // boleh ditawarkan ke pengguna.
        foreach (['Calibri', 'Arial', 'Segoe UI'] as $systemFont) {
            $this->assertNotContains($systemFont, $keys);
        }
    }

    public function test_font_tidak_dikenal_ditolak(): void
    {
        $this->assertFalse(PdfFont::select('font-ngawur'));
        $this->assertNotSame('font-ngawur', PdfFont::currentKey());
    }

    public function test_font_tidak_tersedia_di_lingkungan_tidak_ditawarkan(): void
    {
        // Kunci Tiberius tidak ada di katalog sama sekali.
        $this->assertFalse(PdfFont::isReady('tiberius'));
        $this->assertSame(PdfFont::DEFAULT_KEY, PdfFont::currentKey());
    }

    public function test_memilih_font_men_entah_pilihan_berubah(): void
    {
        $this->assertTrue(PdfFont::select('dejavu_serif'));
        $this->assertSame('dejavu_serif', PdfFont::currentKey());
        $this->assertSame('DejaVu Serif', PdfFont::family());
        $this->assertSame("'DejaVu Serif', serif", PdfFont::stack());

        $this->assertFalse(PdfFont::select('tahoma'));
        $this->assertSame('dejavu_serif', PdfFont::currentKey());
    }

    public function test_font_bawaan_tidak_perlu_aturan_font_face(): void
    {
        PdfFont::select('dejavu_sans');

        // Font bawaan Dompdf sudah dikenali, jadi tidak perlu di-embed.
        $this->assertSame('', PdfFont::faceCss());
        $this->assertSame('DejaVu Sans', PdfFont::family());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function pdfTemplates(): array
    {
        return [
            'surat bebas/tanggungan' => ['pdf.clearance-letter'],
            'surat peminjaman resmi' => ['pdf.official-loan'],
            'laporan peminjaman' => ['pdf.loan-report'],
            'bukti peminjaman (QR)' => ['pdf.loan-qr'],
        ];
    }

    #[DataProvider('pdfTemplates')]
    public function test_setiap_template_pdf_memakai_font_bawaan_tanpa_font_face(string $view): void
    {
        PdfFont::select('dejavu_sans');
        $html = view($view, $this->viewData())->render();

        // Font bawaan Dompdf sudah dikenali sehingga tidak perlu @font-face,
        // namun template tetap harus memakainya.
        $this->assertStringNotContainsString('@font-face', $html);
        $this->assertStringContainsString("font-family: 'DejaVu Sans', sans-serif", $html);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function renderableFonts(): array
    {
        return [
            'DejaVu Sans (bawaan)' => ['dejavu_sans', 'DejaVuSans'],
            'DejaVu Serif (bawaan)' => ['dejavu_serif', 'DejaVuSerif'],
            'Times (Base 14)' => ['times', 'Times'],
            'Helvetica (Base 14)' => ['helvetica', 'Helvetica'],
        ];
    }

    /**
     * Dompdf menulis nama font yang ter-embed pada objek font PDF. Bila font
     * tidak terdaftar, nama tersebut kosong dan teks tidak tampil.
     */
    #[DataProvider('renderableFonts')]
    public function test_font_terpilih_benar_ter_embed_di_pdf(string $key, string $expected): void
    {
        PdfFont::select($key);
        $content = Pdf::loadView('pdf.official-loan', $this->viewData())->output();

        $this->assertStringStartsWith('%PDF', $content);

        $fonts = (new Parser)->parseContent($content)->getFonts();
        $this->assertNotEmpty($fonts, 'PDF tidak memuat font sama sekali.');

        // getFonts() mengembalikan objek Font, bukan array.
        $names = array_map(
            static fn (Font $font): string => (string) $font->getName(),
            array_values($fonts)
        );

        $this->assertNotEmpty(
            array_filter($names, static fn (string $name): bool => stripos($name, $expected) !== false),
            "Font {$expected} tidak ditemukan pada PDF. Font yang ter-embed: ".implode(', ', $names)
        );
    }

    /**
     * Data minimal agar seluruh template PDF dapat dirender.
     *
     * @param  bool  $withRelations  setel relasi item/loanItems (dibutuhkan
     *                               template laporan dan bukti peminjaman)
     * @return array<string, mixed>
     */
    private function viewData(bool $withRelations = true): array
    {
        $item = new Item;
        $item->forceFill([
            'item_code' => 'ITM-001',
            'name' => 'Proyektor Test',
            'category' => 'Elektronik',
        ]);

        $loan = new Loan;

        // `created_at` diisi lewat forceFill karena constructor Eloquent
        // mengabaikan atribut timestamp.
        $loan->forceFill([
            'uuid' => '11111111-2222-3333-4444-555555555555',
            'qr_token' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            'loan_code' => 'PJM-2026-0001',
            'borrower_name' => 'Budi Tester',
            'borrower_email' => 'budi@example.com',
            'borrower_student_id' => '2211082001',
            'status' => 'borrowed',
            'qty' => 1,
            'created_at' => now(),
        ]);

        if ($withRelations) {
            // Template laporan/bukti membaca relasi `item` dan `loanItems`,
            // jadi keduanya disetel di sini supaya view dapat dirender tanpa
            // database.
            $loan->setRelation('item', $item);
            $loan->setRelation('loanItems', new Collection([
                (new LoanItem(['qty' => 1]))->setRelation('item', $item),
            ]));
        }

        return [
            'branding' => Branding::DEFAULTS,
            'loan' => $loan,
            'loans' => new Collection([$loan]),
            'item' => $item,
            'signatoryName' => 'Nofa Hendrayana, S.T.',
            'signatoryNip' => '197907182025211025',
            'signatorySignature' => null,
            'officerName' => 'Nofa Hendrayana, S.T.',
            'officerNip' => '197907182025211025',
            'officerSignature' => null,
            'purpose' => 'Persyaratan pengambilan bebas pustaka',
            'borrowedDate' => now()->subDays(5)->toDateString(),
            'returnDate' => now()->addDays(5)->toDateString(),
            'startDate' => null,
            'endDate' => null,
            'qrDataUri' => 'data:image/png;base64,'.base64_encode(QrPng::generate((string) $loan->uuid, 120)),
            'photoDataUri' => null,
            'eligible' => true,
            'letterNumber' => '001/BEBAS-LAB/PNP/I/2026',
            'letterDate' => now()->toDateString(),
            'laboratory' => 'Laboratorium Komputer',
            'borrower' => [
                'name' => 'Budi Tester',
                'student_id' => '2211082001',
                'email' => 'budi@example.com',
            ],
            'totals' => [
                'total_loans' => 1,
                'total_qty' => 1,
                'outstanding_loans' => 0,
                'outstanding_qty' => 0,
            ],
            'outstanding' => new Collection,
        ];
    }

    private function staff(string $role = 'admin'): User
    {
        return User::factory()->create(['role' => $role]);
    }

    public function test_daftar_font_tersedia_bagi_setiap_petugas(): void
    {
        Sanctum::actingAs($this->staff('assistant'));

        $response = $this->getJson('/api/pdf-font');

        $response->assertStatus(200)->assertJsonStructure(['current', 'fonts']);
        $this->assertContains('dejavu_sans', array_column($response->json('fonts'), 'key'));
        $this->assertNotContains('tahoma', array_column($response->json('fonts'), 'key'));
    }

    public function test_admin_bisa_mengganti_font_pdf(): void
    {
        Sanctum::actingAs($this->staff());

        $response = $this->postJson('/api/pdf-font', ['pdf_font' => 'dejavu_serif']);

        $response->assertStatus(200)->assertJson(['current' => 'dejavu_serif']);
        $this->assertSame('dejavu_serif', PdfFont::currentKey());
    }

    public function test_font_tidak_dikenal_ditolak_dengan_pesan(): void
    {
        Sanctum::actingAs($this->staff());

        $response = $this->postJson('/api/pdf-font', ['pdf_font' => 'font-ngawur']);

        $response->assertStatus(422)->assertJsonStructure(['message', 'fonts']);
        $this->assertNotSame('font-ngawur', PdfFont::currentKey());
    }

    public function test_hanya_admin_yang_bisa_mengganti_font_pdf(): void
    {
        Sanctum::actingAs($this->staff('assistant'));

        $this->postJson('/api/pdf-font', ['pdf_font' => 'dejavu_serif'])->assertStatus(403);
    }

    public function test_font_pdf_membutuhkan_login(): void
    {
        $this->getJson('/api/pdf-font')->assertStatus(401);
    }

    public function test_font_yang_tersimpan_diterapkan_pada_template_pdf(): void
    {
        Sanctum::actingAs($this->staff());
        $this->postJson('/api/pdf-font', ['pdf_font' => 'times'])->assertStatus(200);

        $html = view('pdf.clearance-letter', $this->viewData())->render();

        $this->assertStringContainsString("font-family: 'Times', serif", $html);
    }
}
