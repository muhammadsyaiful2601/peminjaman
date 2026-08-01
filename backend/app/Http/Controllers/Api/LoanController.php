<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\LoanQrCode;
use App\Models\Item;
use App\Models\Loan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

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
                    ->orWhere('uuid', 'like', "%{$search}%");
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

        $loan = Loan::create([
            'uuid' => (string) Str::uuid(),
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

        // Generate QR Code as data URL (base64 PNG) for email
        $qrDataUrl = 'data:image/png;base64,' . base64_encode(
            QrCode::format('png')->size(300)->errorCorrection('H')->generate($loan->uuid)
        );

        // Send QR Code via email to borrower
        try {
            Mail::to($loan->borrower_email)->send(new LoanQrCode($loan, $qrDataUrl));
        } catch (\Exception $e) {
            // Log error but don't fail the transaction
            \Log::error('Failed to send loan QR email: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Peminjaman berhasil dibuat. QR Code telah dikirim ke email peminjam.',
            'loan' => $loan,
            'qr_payload' => $loan->uuid,
        ], 201);
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