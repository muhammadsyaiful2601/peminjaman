<?php

namespace App\Mail;

use App\Models\Loan;
use App\Support\PublicUrl;
use App\Support\QrPng;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

class LoanQrCode extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Loan $loan,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Bukti Peminjaman Barang - Politeknik Negeri Padang - ' . $this->loan->loan_code,
        );
    }

    public function content(): Content
    {
        // Tautan unduh hanya disertakan bila tunnel publik aktif (komputer
        // petugas online). Tanpa tunnel, peminjam tetap mendapat bukti
        // melalui lampiran PDF di email ini.
        $publicUrl = PublicUrl::get();

        return new Content(
            view: 'emails.loan-qr-code',
            with: [
                'attachmentName' => $this->proofFilename(),
                'downloadUrl' => $publicUrl
                    ? $publicUrl . '/api/loans/qr/' . $this->loan->uuid . '/download'
                    : null,
            ],
        );
    }

    public function build(): static
    {
        $this->withSymfonyMessage(function (Email $message): void {
            $logo = DataPart::fromPath(public_path('images/logo_kampus.png'), 'logo-kampus.png', 'image/png');
            $logo->setContentId('logo-kampus@pnp.local');
            $logo->setDisposition('inline');
            $message->addPart($logo);

            // QR Code inline berbentuk PNG agar tampil di semua klien email
            // (Gmail/Outlook memblokir SVG dan gambar ber-URI data).
            $qr = new DataPart(QrPng::generate($this->loan->uuid, 360), 'qr-peminjaman.png', 'image/png');
            $qr->setContentId('qr-peminjaman@pnp.local');
            $qr->setDisposition('inline');
            $message->addPart($qr);

            // PDF bukti peminjaman dilampirkan langsung ke email sehingga dapat
            // dibuka dari perangkat mana pun tanpa bergantung pada URL server
            // aplikasi yang hanya berjalan lokal di komputer petugas.
            $message->attach(
                $this->proofPdf(),
                $this->proofFilename(),
                'application/pdf',
            );
        });

        return $this;
    }

    /**
     * Nama file lampiran bukti peminjaman (sama dengan unduhan di aplikasi).
     */
    public function proofFilename(): string
    {
        $safeName = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $this->loan->borrower_name);

        return 'bukti-peminjaman-' . trim($safeName, '-') . '.pdf';
    }

    /**
     * Susun PDF bukti peminjaman (identik dengan endpoint unduh QR di aplikasi).
     */
    private function proofPdf(): string
    {
        $qrSvg = QrCode::size(300)->margin(2)->errorCorrection('H')->generate($this->loan->uuid);
        $qrDataUri = 'data:image/svg+xml;base64,' . base64_encode($qrSvg);

        $photoDataUri = null;
        $photoPath = $this->loan->borrow_photo
            ? Storage::disk('public')->path($this->loan->borrow_photo)
            : null;
        if ($this->loan->borrow_photo && file_exists($photoPath)) {
            $photoData = file_get_contents($photoPath);
            $photoDataUri = 'data:image/jpeg;base64,' . base64_encode($photoData);
        }

        return Pdf::loadView('pdf.loan-qr', [
            'loan' => $this->loan->loadMissing(['item', 'loanItems.item']),
            'qrDataUri' => $qrDataUri,
            'photoDataUri' => $photoDataUri,
        ])->output();
    }
}