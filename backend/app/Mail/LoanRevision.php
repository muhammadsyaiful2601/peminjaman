<?php

namespace App\Mail;

use App\Models\Loan;
use App\Support\PublicUrl;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class LoanRevision extends LoanQrCode
{
    public function __construct(
        Loan $loan,
        public int $previousQuantity,
        public int $updatedQuantity,
    ) {
        parent::__construct($loan);
        $this->isRevision = true;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Revisi Peminjaman Barang - '.$this->loan->loan_code,
        );
    }

    public function content(): Content
    {
        $publicUrl = PublicUrl::get();

        return new Content(
            view: 'emails.loan-qr-code',
            with: [
                'attachmentName' => $this->proofFilename(),
                'downloadUrl' => $publicUrl
                    ? $publicUrl.'/api/loans/qr/'.($this->loan->qr_token ?: $this->loan->uuid).'/download?revision=1'
                    : null,
            ],
        );
    }

    public function proofFilename(): string
    {
        $safeName = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $this->loan->borrower_name);

        return 'revisi-baru-peminjaman-'.trim($safeName, '-').'-'.$this->loan->loan_code.'.pdf';
    }
}