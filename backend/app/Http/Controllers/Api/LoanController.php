<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\LoanQrCode;
use App\Models\Item;
use App\Models\Loan;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Smalot\PdfParser\Parser as PdfParser;

class LoanController extends Controller
{
    public function index(Request $request)
    {
        $query = Loan::with(['item', 'creator', 'verifier']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('borrower_name', 'like', "%{$search}%")
                    ->orWhere('borrower_email', 'like', "%{$search}%")
                    ->orWhere('borrower_student_id', 'like', "%{$search}%")
                    ->orWhere('uuid', 'like', "%{$search}%")
                    ->orWhere('loan_code', 'like', "%{$search}%");
            });
        }

        $loans = $query->orderByDesc('created_at')->paginate($request->per_page ?? 15);

        return response()->json($loans);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'item_id' => ['required', 'exists:items,id'],
            'qty' => ['required', 'integer', 'min:1'],
            'borrower_name' => ['required', 'string', 'max:255'],
            'borrower_email' => ['required', 'email', 'max:255'],
            'borrower_phone' => ['nullable', 'string', 'max:20'],
            'borrower_student_id' => ['nullable', 'string', 'max:50'],
            'borrow_photo' => ['required', 'image', 'max:5120'],
        ]);

        $item = Item::findOrFail($validated['item_id']);

        if ($item->stock < $validated['qty']) {
            return response()->json([
                'message' => 'Stok barang tidak mencukupi. Stok tersedia: ' . $item->stock,
            ], 422);
        }

        $photoPath = $request->file('borrow_photo')->store('borrow-photos', 'public');

        // Generate unique loan code (e.g. PJM-2026-0001)
        $loanCode = $this->generateLoanCode();

        $loan = Loan::create([
            'uuid' => (string) Str::uuid(),
            'loan_code' => $loanCode,
            'item_id' => $item->id,
            'qty' => $validated['qty'],
            'borrower_name' => $validated['borrower_name'],
            'borrower_email' => $validated['borrower_email'],
            'borrower_phone' => $validated['borrower_phone'] ?? null,
            'borrower_student_id' => $validated['borrower_student_id'] ?? null,
            'borrow_photo' => $photoPath,
            'status' => 'pending',
            'created_by' => $request->user()->id,
        ]);

        $loan->load(['item', 'creator']);

        // Send QR Code via email to borrower
        try {
            Mail::to($loan->borrower_email)->send(new LoanQrCode($loan));
        } catch (\Exception $e) {
            \Log::error('Failed to send loan QR email: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Peminjaman berhasil dibuat. QR Code telah dikirim ke email peminjam.',
            'loan' => $loan,
            'qr_payload' => $loan->uuid,
        ], 201);
    }

    /**
     * Generate unique loan code: PJM-YYYY-XXXX
     */
    private function generateLoanCode(): string
    {
        $year = date('Y');
        $prefix = "PJM-{$year}-";

        // Find the highest sequence for this year
        $lastLoan = Loan::where('loan_code', 'like', $prefix . '%')
            ->orderBy('loan_code', 'desc')
            ->first();

        $sequence = 1;
        if ($lastLoan) {
            $lastSequence = (int) substr($lastLoan->loan_code, -4);
            $sequence = $lastSequence + 1;
        }

        return $prefix . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    public function show(Loan $loan)
    {
        $loan->load(['item', 'creator', 'verifier']);

        return response()->json([
            'loan' => $loan,
        ]);
    }

    public function showByUuid(string $uuid)
    {
        $loan = Loan::with(['item', 'creator', 'verifier'])
            ->where('uuid', $uuid)
            ->first();

        if (! $loan) {
            return response()->json([
                'message' => 'Transaksi tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'loan' => $loan,
        ]);
    }

    /**
     * Lookup loan by loan code (for manual code entry).
     */
    public function showByCode(string $code)
    {
        $loan = Loan::with(['item', 'creator', 'verifier'])
            ->where('loan_code', $code)
            ->first();

        if (! $loan) {
            return response()->json([
                'message' => 'Transaksi tidak ditemukan. Periksa kembali kode peminjaman.',
            ], 404);
        }

        return response()->json([
            'loan' => $loan,
        ]);
    }

    /**
     * Upload PDF and extract UUID/loan_code to find the loan.
     */
    public function uploadPdf(Request $request)
    {
        $request->validate([
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:5120'],
        ]);

        $pdfFile = $request->file('pdf');

        try {
            $parser = new PdfParser();
            $pdf = $parser->parseFile($pdfFile->getPathname());
            $text = $pdf->getText();

            // Try to find UUID pattern
            $uuidPattern = '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i';
            $loan = null;

            if (preg_match($uuidPattern, $text, $matches)) {
                $loan = Loan::with(['item', 'creator', 'verifier'])
                    ->where('uuid', $matches[0])
                    ->first();
            }

            // If UUID not found, try loan code pattern PJM-YYYY-XXXX
            if (! $loan) {
                $codePattern = '/PJM-\d{4}-\d{4}/i';
                if (preg_match($codePattern, $text, $matches)) {
                    $loan = Loan::with(['item', 'creator', 'verifier'])
                        ->where('loan_code', $matches[0])
                        ->first();
                }
            }

            if (! $loan) {
                return response()->json([
                    'message' => 'QR Code atau kode peminjaman tidak ditemukan dalam PDF. Pastikan ini adalah bukti peminjaman yang valid.',
                ], 404);
            }

            return response()->json([
                'loan' => $loan,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal membaca PDF. Pastikan file yang diupload adalah bukti peminjaman yang valid.',
            ], 422);
        }
    }

    /**
     * Download QR Code + loan details as PDF (public - UUID acts as security token).
     */
    public function downloadQr(string $uuid)
    {
        $loan = Loan::with('item')->where('uuid', $uuid)->first();

        if (! $loan) {
            return response()->json([
                'message' => 'Transaksi tidak ditemukan',
            ], 404);
        }

        // Generate QR Code as SVG and convert to base64 data URI
        $qrSvg = QrCode::size(300)->margin(2)->errorCorrection('H')->generate($loan->uuid);
        $qrDataUri = 'data:image/svg+xml;base64,' . base64_encode($qrSvg);

        // Get borrower photo as base64 for PDF embedding
        $photoDataUri = null;
        $photoPath = storage_path('app/public/' . $loan->borrow_photo);
        if ($loan->borrow_photo && file_exists($photoPath)) {
            $photoData = file_get_contents($photoPath);
            $photoDataUri = 'data:image/jpeg;base64,' . base64_encode($photoData);
        }

        $pdf = Pdf::loadView('pdf.loan-qr', [
            'loan' => $loan,
            'qrDataUri' => $qrDataUri,
            'photoDataUri' => $photoDataUri,
        ]);

        $filename = 'bukti-peminjaman-' . $loan->borrower_name . '.pdf';

        return $pdf->download($filename);
    }

    public function approve(Request $request, Loan $loan)
    {
        if ($loan->status !== 'pending') {
            return response()->json([
                'message' => 'Transaksi tidak dalam status pending',
            ], 422);
        }

        $item = $loan->item;

        if ($item->stock < $loan->qty) {
            return response()->json([
                'message' => 'Stok barang tidak mencukupi. Stok tersedia: ' . $item->stock,
            ], 422);
        }

        // Decrease stock
        $item->decrement('stock', $loan->qty);

        $loan->update([
            'status' => 'borrowed',
            'borrowed_at' => now(),
            'verified_by' => $request->user()->id,
        ]);

        $loan->load(['item', 'creator', 'verifier']);

        return response()->json([
            'message' => 'Peminjaman disetujui. Barang telah diserahkan.',
            'loan' => $loan,
        ]);
    }

    public function reject(Request $request, Loan $loan)
    {
        if ($loan->status !== 'pending') {
            return response()->json([
                'message' => 'Transaksi tidak dalam status pending',
            ], 422);
        }

        $loan->update([
            'status' => 'rejected',
            'verified_by' => $request->user()->id,
        ]);

        $loan->load(['item', 'creator', 'verifier']);

        return response()->json([
            'message' => 'Peminjaman ditolak.',
            'loan' => $loan,
        ]);
    }

    public function returnItem(Request $request, Loan $loan)
    {
        if ($loan->status !== 'borrowed') {
            return response()->json([
                'message' => 'Transaksi tidak dalam status dipinjam',
            ], 422);
        }

        $validated = $request->validate([
            'condition_on_return' => ['required', 'string', 'in:bagus,rusak,hilang'],
            'condition_note' => ['nullable', 'string', 'max:1000'],
        ]);

        // Increase stock back
        $loan->item->increment('stock', $loan->qty);

        $condition = $validated['condition_on_return'];
        if (! empty($validated['condition_note'])) {
            $condition .= ' - ' . $validated['condition_note'];
        }

        $loan->update([
            'status' => 'returned',
            'returned_at' => now(),
            'verified_by' => $request->user()->id,
            'condition_on_return' => $condition,
        ]);

        $loan->load(['item', 'creator', 'verifier']);

        return response()->json([
            'message' => 'Barang berhasil dikembalikan. Stok telah diperbarui.',
            'loan' => $loan,
        ]);
    }
}