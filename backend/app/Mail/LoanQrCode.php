<?php

namespace App\Mail;

use App\Models\Loan;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LoanQrCode extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Loan $loan,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Bukti Peminjaman Barang - ' . $this->loan->item->name,
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
}