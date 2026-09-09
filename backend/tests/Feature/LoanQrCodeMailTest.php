<?php

namespace Tests\Feature;

use App\Mail\LoanQrCode;
use App\Models\Item;
use App\Models\Loan;
use App\Models\User;
use App\Support\PublicUrl;
use App\Support\QrPng;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\AbstractMultipartPart;
use Symfony\Component\Mime\Part\DataPart;
use Tests\TestCase;

class LoanQrCodeMailTest extends TestCase
{
    use RefreshDatabase;

    private function createLoan(): Loan
    {
        $user = User::factory()->create();
        $item = Item::create([
            'item_code' => 'ITM-001',
            'name' => 'Proyektor Test',
            'category' => 'Elektronik',
            'stock' => 5,
        ]);

        $loan = Loan::create([
            'uuid' => '11111111-2222-3333-4444-555555555555',
            'loan_code' => 'PJM-2026-0001',
            'item_id' => $item->id,
            'qty' => 1,
            'borrower_name' => 'Budi Tester',
            'borrower_email' => 'budi@example.com',
            'status' => 'borrowed',
            'borrowed_at' => now(),
            'created_by' => $user->id,
            'verified_by' => $user->id,
        ]);

        $loan->loanItems()->create(['item_id' => $item->id, 'qty' => 1]);

        return $loan->refresh();
    }

    public function test_qr_png_generator_produces_valid_png_image(): void
    {
        $png = QrPng::generate('11111111-2222-3333-4444-555555555555', 320);

        $this->assertNotEmpty($png);
        // Magic number PNG: \x89PNG\r\n\x1a\n
        $this->assertSame("\x89PNG\r\n\x1a\n", substr($png, 0, 8));
    }

    /**
     * Ratakan struktur MIME (multipart/mixed -> related -> alternative)
     * menjadi daftar part daun agar mudah diperiksa.
     */
    private function flattenParts(array $parts): array
    {
        $flat = [];
        foreach ($parts as $part) {
            if ($part instanceof AbstractMultipartPart) {
                $flat = array_merge($flat, $this->flattenParts($part->getParts()));
                continue;
            }
            $flat[] = $part;
        }

        return $flat;
    }

    public function test_loan_proof_email_attaches_pdf_and_inline_qr_without_local_url(): void
    {
        $loan = $this->createLoan();
        $mailable = new LoanQrCode($loan);
        $mailer = app('mailer');
        $mailer->to('budi@example.com')->send($mailable);

        $sent = $mailer->getSymfonyTransport()->messages()->first();
        $this->assertNotNull($sent, 'Email bukti peminjaman tidak berhasil dibangun.');

        $message = $sent->getOriginalMessage();
        $this->assertInstanceOf(Email::class, $message);

        // Catatan: pada symfony/mime versi ini Email::attach() memakai addPart(),
        // sehingga semua part (termasuk lampiran) harus diperiksa dari body MIME.
        $dataParts = collect($this->flattenParts([$message->getBody()]))
            ->filter(fn ($part) => $part instanceof DataPart)
            ->values();

        // 1. Lampiran PDF bukti peminjaman harus ada dan berisi PDF valid.
        $pdf = $dataParts->first(
            fn (DataPart $part) => $part->getContentType() === 'application/pdf',
        );
        $this->assertNotNull($pdf, 'Lampiran PDF bukti peminjaman tidak ditemukan.');
        $this->assertSame('bukti-peminjaman-Budi-Tester.pdf', $pdf->getFilename());
        $this->assertSame('attachment', $pdf->getDisposition());
        $this->assertStringStartsWith('%PDF', $pdf->getBody());

        // 2. Gambar QR inline (PNG, CID) harus ada agar tampil di semua klien email.
        $qr = $dataParts->first(
            fn (DataPart $part) => $part->getContentType() === 'image/png'
                && $part->getFilename() === 'qr-peminjaman.png',
        );
        $this->assertNotNull($qr, 'Gambar QR inline tidak ditemukan.');
        $this->assertSame('inline', $qr->getDisposition());
        $this->assertSame(['qr-peminjaman@pnp.local'], (array) $qr->getPreparedHeaders()->getHeaderBody('Content-ID'));
        $this->assertSame("\x89PNG\r\n\x1a\n", substr($qr->getBody(), 0, 8));

        // 3. Logo institusi tetap ter-embed.
        $logo = $dataParts->first(fn (DataPart $part) => $part->getFilename() === 'logo-kampus.png');
        $this->assertNotNull($logo, 'Logo kampus tidak ditemukan.');

        // 4. Badan email memakai CID QR + kode peminjaman.
        $html = $message->getHtmlBody();
        $this->assertStringContainsString('cid:qr-peminjaman@pnp.local', $html);
        $this->assertStringContainsString('PJM-2026-0001', $html);
        $this->assertStringContainsString('bukti-peminjaman-Budi-Tester.pdf', $html);

        // 5. Regresi: email tidak boleh lagi memuat URL server lokal aplikasi.
        $this->assertStringNotContainsString('127.0.0.1', $html);
        $this->assertStringNotContainsString('localhost', $html);
    }

    public function test_download_button_is_included_when_tunnel_url_is_active(): void
    {
        $loan = $this->createLoan();

        config(['app.public_url' => 'https://demo-tunnel.trycloudflare.com']);

        $mailer = app('mailer');
        $mailer->to('budi@example.com')->send(new LoanQrCode($loan));

        $message = $mailer->getSymfonyTransport()->messages()->first()->getOriginalMessage();
        $html = $message->getHtmlBody();

        // Tombol unduh memakai URL publik tunnel, bukan URL lokal.
        $this->assertStringContainsString(
            'https://demo-tunnel.trycloudflare.com/api/loans/qr/' . $loan->uuid . '/download',
            $html,
        );
        $this->assertStringContainsString('Unduh Bukti Peminjaman (PDF)', $html);
        $this->assertStringNotContainsString('127.0.0.1', $html);
    }

    public function test_public_url_falls_back_to_file_written_by_desktop_app(): void
    {
        // Env tidak diisi pada saat test -> helper membaca file yang
        // dituliskan aplikasi desktop saat tunnel menyala di tengah sesi.
        $path = storage_path('app/desktop-public-url.txt');
        file_put_contents($path, "https://file-tunnel.trycloudflare.com\n");

        try {
            $this->assertSame('https://file-tunnel.trycloudflare.com', PublicUrl::get());

            // Nilai rusak/kosong diabaikan.
            file_put_contents($path, 'bukan-url');
            $this->assertNull(PublicUrl::get());
        } finally {
            @unlink($path);
        }
    }

    public function test_public_url_is_null_when_tunnel_is_inactive(): void
    {
        $path = storage_path('app/desktop-public-url.txt');
        @unlink($path);

        $this->assertNull(PublicUrl::get());
    }
}
