<?php

namespace App\Mail;

use App\Models\Loan;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
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
        return new Content(
            view: 'emails.loan-qr-code',
            with: [
                'downloadUrl' => url('/api/loans/qr/' . $this->loan->uuid . '/download'),
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
        });

        return $this;
    }
}