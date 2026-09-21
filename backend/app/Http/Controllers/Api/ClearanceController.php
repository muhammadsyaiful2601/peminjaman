<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClearanceLetter;
use App\Models\Loan;
use App\Support\Branding;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Surat Keterangan Bebas Laboratorium.
 *
 * Isi surat diambil dari data peminjaman: surat hanya dapat diterbitkan bila
 * peminjam tidak memiliki transaksi yang belum dikembalikan (`borrowed` atau
 * `pending`). Jika masih ada tanggungan, permintaan unduh ditolak dengan
 * HTTP 422 beserta daftar barang yang belum kembali.
 */
class ClearanceController extends Controller
{
    /** Status transaksi yang berarti barang belum kembali ke laboratorium. */
    private const OUTSTANDING_STATUSES = ['borrowed', 'pending'];

    private const STATUS_LABELS = [
        'pending' => 'Menunggu',
        'borrowed' => 'Dipinjam',
        'returned' => 'Dikembalikan',
        'rejected' => 'Ditolak',
    ];

    /**
     * Daftar peminjam (dikelompokkan dari data peminjaman) beserta status
     * kelayakan bebas laboratorium. Dipakai halaman "Bebas Labor".
     */
    public function borrowers(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));

        $loans = Loan::query()
            ->withSum('loanItems', 'qty')
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $sub) use ($search) {
                    $sub->where('borrower_name', 'like', "%{$search}%")
                        ->orWhere('borrower_student_id', 'like', "%{$search}%")
                        ->orWhere('borrower_email', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->get([
                'id',
                'loan_code',
                'item_id',
                'qty',
                'borrower_name',
                'borrower_student_id',
                'borrower_email',
                'status',
                'created_at',
            ]);

        $borrowers = $loans
            ->groupBy(fn (Loan $loan) => $this->identityKey($loan))
            ->map(fn (Collection $rows) => $this->summarizeBorrower($rows))
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return response()->json([
            'data' => $borrowers,
            'meta' => [
                'total' => $borrowers->count(),
                'search' => $search,
            ],
        ]);
    }

    /**
     * Rincian peminjaman satu peminjam: transaksi yang belum kembali (jika ada),
     * riwayat pengembalian, dan kelayakan penerbitan surat bebas labor.
     */
    public function detail(Request $request)
    {
        [$studentId, $email, $name] = $this->identifier($request);

        $loans = $this->borrowerLoans($studentId, $email, $name);

        if ($loans->isEmpty()) {
            return response()->json([
                'message' => 'Data peminjaman peminjam tidak ditemukan.',
            ], 404);
        }

        $outstanding = $loans->whereIn('status', self::OUTSTANDING_STATUSES)->values();
        $returned = $loans->where('status', 'returned')->values();

        return response()->json([
            'borrower' => $this->borrowerIdentity($loans),
            'eligible' => $outstanding->isEmpty(),
            'totals' => $this->totals($loans, $outstanding, $returned),
            'outstanding' => $outstanding->map(fn (Loan $loan) => $this->formatLoan($loan))->values()->all(),
            'history' => $returned->map(fn (Loan $loan) => $this->formatLoan($loan))->values()->all(),
        ]);
    }

    /**
     * Terbitkan surat bebas laboratorium (PDF berkop surat) dari data peminjaman.
     */
    public function download(Request $request)
    {
        [$studentId, $email, $name] = $this->identifier($request);

        $validated = $request->validate([
            'purpose' => ['required', 'string', 'max:255'],
            'letter_number' => ['nullable', 'string', 'max:100'],
            'letter_date' => ['nullable', 'date'],
            'laboratory' => ['nullable', 'string', 'max:255'],
            'signatory_name' => ['required', 'string', 'max:255'],
            'signatory_nip' => ['required', 'string', 'max:100'],
        ]);

        $loans = $this->borrowerLoans($studentId, $email, $name);

        if ($loans->isEmpty()) {
            throw ValidationException::withMessages([
                'student_id' => 'Data peminjaman peminjam tersebut tidak ditemukan.',
            ]);
        }

        $outstanding = $loans->whereIn('status', self::OUTSTANDING_STATUSES)->values();
        $returned = $loans->where('status', 'returned')->values();
        $borrower = $this->borrowerIdentity($loans);
        $eligible = $outstanding->isEmpty();
        $letterDate = \Illuminate\Support\Carbon::parse($validated['letter_date'] ?? now()->toDateString());
        $clearanceLetter = ClearanceLetter::create([
            'letter_date' => $letterDate->toDateString(),
            'letter_number' => null,
        ]);
        $letterNumber = sprintf(
            '%03d/BEBAS-LAB/PNP/%s/%s',
            $clearanceLetter->id,
            $this->romanMonth((int) $letterDate->month),
            $letterDate->year,
        );
        $clearanceLetter->update(['letter_number' => $letterNumber]);

        // Surat selalu dapat diunduh oleh petugas. Bila masih ada barang yang belum
        // dikembalikan, isi surat otomatis menjadi Surat Keterangan Tanggungan
        // (daftar barang yang belum kembali) agar dokumen tetap sesuai data.
        $pdf = Pdf::loadView('pdf.clearance-letter', [
            'branding' => Branding::pdfData(),
            'borrower' => $borrower,
            'loans' => $returned,
            'outstanding' => $outstanding,
            'eligible' => $eligible,
            'purpose' => $validated['purpose'],
            'letterNumber' => $letterNumber,
            'letterDate' => $letterDate->toDateString(),
            'laboratory' => trim((string) ($validated['laboratory'] ?? '')),
            'signatoryName' => $validated['signatory_name'],
            'signatoryNip' => $validated['signatory_nip'],
            'totals' => $this->totals($loans, $outstanding, $returned),
        ]);

        $safeName = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $borrower['name']);
        $filename = ($eligible ? 'surat-bebas-labor-' : 'surat-tanggungan-labor-')
            . trim((string) $safeName, '-') . '.pdf';

        return $pdf->download($filename)->withHeaders([
            'X-Clearance-Eligible' => $eligible ? '1' : '0',
            'X-Clearance-Borrower' => $borrower['student_id'] !== '' ? $borrower['student_id'] : $borrower['email'],
            'X-Clearance-Letter-Number' => $letterNumber,
        ]);
    }

    private function romanMonth(int $month): string
    {
        return [
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
            7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
        ][$month] ?? 'I';
    }

    /**
     * Kunci identitas peminjam.
     *
     * NIM dipakai sebagai kunci utama bila ada. Bila NIM kosong (umum pada
     * peminjaman yang hanya mengisi nama/email), email + nama dipakai agar
     * peminjam berbeda yang memakai email sama tidak tergabung menjadi satu.
     */
    private function identityKey(Loan $loan): string
    {
        $studentId = $this->normalizeText($loan->borrower_student_id);

        if ($studentId !== '') {
            return 'nim:' . $studentId;
        }

        return 'email:' . $this->normalizeText($loan->borrower_email)
            . '|name:' . $this->normalizeName($loan->borrower_name);
    }

    /**
     * Ambil NIM/email/nama dari request untuk mencari data peminjaman peminjam.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function identifier(Request $request): array
    {
        $validated = $request->validate([
            'student_id' => ['nullable', 'string', 'max:50'],
            'borrower_email' => ['nullable', 'email', 'max:255'],
            'borrower_name' => ['nullable', 'string', 'max:255'],
        ]);

        $studentId = trim((string) ($validated['student_id'] ?? ''));
        $email = trim((string) ($validated['borrower_email'] ?? ''));
        $name = trim((string) ($validated['borrower_name'] ?? ''));

        if ($studentId === '' && $email === '') {
            throw ValidationException::withMessages([
                'student_id' => 'Isi NIM atau email peminjam untuk mencari data peminjaman.',
            ]);
        }

        return [$studentId, $email, $name];
    }

    /**
     * Semua transaksi milik satu peminjam.
     *
     * Aturan pencocokan:
     * 1. NIM (bila ada) selalu diutamakan.
     * 2. Transaksi lama yang belum mengisi NIM tetap diikutkan bila email + nama
     *    peminjam sama, supaya surat tidak terbit saat barang masih ditahan.
     * 3. Transaksi peminjam lain yang kebetulan memakai email sama tetapi namanya
     *    berbeda tidak diikutkan.
     *
     * @return Collection<int, Loan>
     */
    private function borrowerLoans(string $studentId, string $email, string $name): Collection
    {
        $normalizedStudentId = $this->normalizeText($studentId);
        $normalizedEmail = $this->normalizeText($email);
        $normalizedName = $this->normalizeName($name);

        $loans = Loan::query()
            ->with(['item', 'loanItems.item'])
            ->orderByDesc('created_at')
            ->where(function (Builder $query) use ($normalizedStudentId, $normalizedEmail) {
                if ($normalizedStudentId !== '') {
                    $query->whereRaw('LOWER(TRIM(borrower_student_id)) = ?', [$normalizedStudentId]);
                }

                if ($normalizedEmail === '') {
                    return;
                }

                $normalizedStudentId !== ''
                    ? $query->orWhereRaw('LOWER(TRIM(borrower_email)) = ?', [$normalizedEmail])
                    : $query->whereRaw('LOWER(TRIM(borrower_email)) = ?', [$normalizedEmail]);
            })
            ->get();

        return $loans
            ->filter(function (Loan $loan) use ($normalizedStudentId, $normalizedName) {
                if ($normalizedStudentId !== '' && $this->normalizeText($loan->borrower_student_id) === $normalizedStudentId) {
                    return true;
                }

                return $normalizedName === '' || $this->normalizeName($loan->borrower_name) === $normalizedName;
            })
            ->values();
    }

    private function normalizeText(?string $value): string
    {
        return strtolower(trim((string) $value));
    }

    private function normalizeName(?string $value): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', trim((string) $value));

        return strtolower((string) $collapsed);
    }

    /**
     * Ringkasan satu peminjam untuk daftar pencarian.
     *
     * @param  Collection<int, Loan>  $rows
     */
    private function summarizeBorrower(Collection $rows): array
    {
        $outstanding = $rows->whereIn('status', self::OUTSTANDING_STATUSES);
        $returned = $rows->where('status', 'returned');
        $latest = $rows->first();

        return [
            'student_id' => $this->firstStudentId($rows),
            'name' => (string) ($latest->borrower_name ?? ''),
            'email' => (string) ($latest->borrower_email ?? ''),
            'total_loans' => $rows->count(),
            'returned_loans' => $returned->count(),
            'outstanding_loans' => $outstanding->count(),
            'total_qty' => (int) $rows->sum(fn (Loan $loan) => $this->quantity($loan)),
            'outstanding_qty' => (int) $outstanding->sum(fn (Loan $loan) => $this->quantity($loan)),
            'last_loan_at' => $latest?->created_at?->toIso8601String(),
            'eligible' => $outstanding->isEmpty(),
        ];
    }

    /**
     * Identitas peminjam berdasarkan transaksi terbaru.
     *
     * @param  Collection<int, Loan>  $loans
     */
    private function borrowerIdentity(Collection $loans): array
    {
        $latest = $loans->first();

        return [
            'name' => (string) ($latest->borrower_name ?? ''),
            'student_id' => $this->firstStudentId($loans),
            'email' => (string) ($latest->borrower_email ?? ''),
            'last_loan_at' => $latest?->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  Collection<int, Loan>  $loans
     */
    private function firstStudentId(Collection $loans): string
    {
        $studentId = $loans
            ->pluck('borrower_student_id')
            ->first(fn ($value) => trim((string) $value) !== '');

        return trim((string) $studentId);
    }

    /**
     * @param  Collection<int, Loan>  $loans
     * @param  Collection<int, Loan>  $outstanding
     * @param  Collection<int, Loan>  $returned
     */
    private function totals(Collection $loans, Collection $outstanding, Collection $returned): array
    {
        return [
            'total_loans' => $loans->count(),
            'total_qty' => (int) $loans->sum(fn (Loan $loan) => $this->quantity($loan)),
            'outstanding_loans' => $outstanding->count(),
            'outstanding_qty' => (int) $outstanding->sum(fn (Loan $loan) => $this->quantity($loan)),
            'returned_loans' => $returned->count(),
        ];
    }

    /**
     * Satu transaksi dalam bentuk array untuk API dan isi surat.
     */
    private function formatLoan(Loan $loan): array
    {
        $items = $loan->loanItems->isNotEmpty()
            ? $loan->loanItems->map(fn ($loanItem) => [
                'name' => $loanItem->item?->name ?? 'Barang tidak tersedia',
                'item_code' => $loanItem->item?->item_code ?? '-',
                'qty' => (int) $loanItem->qty,
            ])->values()
            : collect([[
                'name' => $loan->item?->name ?? 'Barang tidak tersedia',
                'item_code' => $loan->item?->item_code ?? '-',
                'qty' => (int) $loan->qty,
            ]]);

        return [
            'id' => $loan->id,
            'loan_code' => $loan->loan_code,
            'status' => $loan->status,
            'status_label' => self::STATUS_LABELS[$loan->status] ?? $loan->status,
            'qty' => $this->quantity($loan),
            'items' => $items->all(),
            'item_summary' => $items->pluck('name')->join(', '),
            'borrowed_at' => $loan->borrowed_at?->toIso8601String(),
            'returned_at' => $loan->returned_at?->toIso8601String(),
            'created_at' => $loan->created_at?->toIso8601String(),
            'condition_on_return' => $loan->condition_on_return,
        ];
    }

    /**
     * Jumlah unit satu transaksi: memakai `loan_items`, dengan `qty` pada `loans`
     * sebagai fallback untuk data transaksi lama.
     */
    private function quantity(Loan $loan): int
    {
        if ($loan->relationLoaded('loanItems')) {
            return $loan->loanItems->isNotEmpty()
                ? (int) $loan->loanItems->sum('qty')
                : (int) $loan->qty;
        }

        $sum = (int) ($loan->loan_items_sum_qty ?? 0);

        return $sum > 0 ? $sum : (int) $loan->qty;
    }
}
