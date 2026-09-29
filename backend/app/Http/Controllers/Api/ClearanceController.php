<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClearanceLetter;
use App\Models\Loan;
use App\Models\Student;
use App\Support\BorrowerType;
use App\Support\Branding;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Surat Keterangan Bebas Laboratorium.
 *
 * Daftar pada halaman "Bebas Labor" bersumber dari data mahasiswa (sama dengan
 * halaman Data Mahasiswa), lalu dilengkapi ringkasan transaksi peminjaman orang
 * tersebut. Aturan penerbitan surat:
 *
 * 1. Surat hanya dapat diterbitkan untuk peminjam yang **tidak memiliki
 *    tanggungan**. Peminjam yang pernah meminjam dan semua barangnya sudah
 *    dikembalikan **maupun** mahasiswa yang belum pernah meminjam barang sama
 *    sekali (termasuk yang seluruh transaksinya `rejected`) sama-sama berstatus
 *    *bebas labor*. Untuk peminjam tanpa riwayat, surat menyatakan bahwa tidak
 *    ada transaksi peminjaman yang tercatat atas namanya.
 * 2. Bila seluruh barang sudah dikembalikan, surat diterbitkan sebagai Surat
 *    Keterangan Bebas Laboratorium.
 * 3. Bila masih ada barang yang belum dikembalikan (`borrowed`/`pending`), surat
 *    otomatis menjadi Surat Keterangan Tanggungan Peminjaman Laboratorium.
 *
 * Transaksi lama yang dibuat sebelum data mahasiswa tersedia tetap terhubung ke
 * mahasiswanya: pencocokan hanya memerlukan **salah satu** data yang sama (NIM,
 * email, nama, atau nomor telepon). Transaksi yang tidak cocok dengan data
 * mahasiswa mana pun tetap tampil sebagai peminjam manual.
 */
class ClearanceController extends Controller
{
    /** Status transaksi yang berarti barang belum kembali ke laboratorium. */
    private const OUTSTANDING_STATUSES = ['borrowed', 'pending'];

    /** Status transaksi yang berarti peminjam benar-benar pernah meminjam. */
    private const ACTIVE_STATUSES = ['pending', 'borrowed', 'returned'];

    private const STATUS_LABELS = [
        'pending' => 'Menunggu',
        'borrowed' => 'Dipinjam',
        'returned' => 'Dikembalikan',
        'rejected' => 'Ditolak',
    ];

    /**
     * Kelayakan surat: bebas labor, masih bertanggungan, atau belum pernah
     * meminjam. Status `belum_pernah_meminjam` tetap berstatus **bebas labor**
     * (`eligible`) karena tidak ada barang yang ditahan; suratnya menerangkan
     * bahwa tidak ada transaksi peminjaman yang tercatat.
     */
    private const LETTER_BEBAS_LABOR = 'bebas_labor';

    private const LETTER_TANGGUNGAN = 'tanggungan';

    private const LETTER_NONE = 'belum_pernah_meminjam';

    /**
     * Daftar mahasiswa (sumber data sama dengan halaman "Data Mahasiswa")
     * beserta ringkasan peminjaman dan kelayakan surat bebas laboratorium.
     */
    public function borrowers(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));

        $students = Student::query()->orderBy('name')->get();
        $attribution = $this->attributeLoans($students, $this->allLoans());

        $rows = collect();

        foreach ($students as $student) {
            if ($search !== '' && ! $this->studentMatchesSearch($student, $search)) {
                continue;
            }

            $rows->push($this->summarizeBorrower(
                collect($attribution['byStudent'][$student->id] ?? []),
                $this->studentIdentity($student),
            ));
        }

        // Transaksi yang belum dapat dicocokkan ke data mahasiswa (peminjaman
        // manual) tetap ditampilkan agar tanggungannya tetap terpantau.
        $rows = $rows
            ->concat($this->unregisteredBorrowerRows($attribution['unowned'], $search))
            ->sortBy(fn (array $row) => $row['name'], SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return response()->json([
            'data' => $rows,
            'meta' => [
                'total' => $rows->count(),
                'search' => $search,
            ],
        ]);
    }

    /**
     * Rincian peminjaman satu peminjam: identitas dari data mahasiswa (bila
     * terdaftar), transaksi yang belum kembali (jika ada), riwayat pengembalian,
     * dan kelayakan penerbitan surat bebas labor.
     */
    public function detail(Request $request)
    {
        [$studentId, $email, $name] = $this->identifier($request);

        $student = $this->findStudent($studentId, $email, $name);

        // Mahasiswa terdaftar: transaksi dicocokkan lewat NIM/email/nama/telepon
        // seperti daftar pada halaman Bebas Labor (supaya transaksi lama ikut).
        // Peminjam manual (tanpa data mahasiswa) dicocokkan dari identitasnya.
        $loans = $student !== null
            ? $this->loansOwnedByStudent($student)
            : $this->borrowerLoans($studentId, $email, $name);

        if ($student === null && $loans->isEmpty()) {
            return response()->json([
                'message' => 'Data peminjaman peminjam tidak ditemukan.',
            ], 404);
        }

        // Transaksi `rejected` tidak dihitung: peminjam tersebut tidak pernah
        // menerima barang, sehingga tidak dianggap "pernah meminjam".
        $records = $loans->whereIn('status', self::ACTIVE_STATUSES)->values();
        $outstanding = $records->whereIn('status', self::OUTSTANDING_STATUSES)->values();
        $returned = $records->where('status', 'returned')->values();
        $hasLoans = $records->isNotEmpty();
        // Tidak ada barang yang masih ditahan berarti bebas labor — termasuk
        // mahasiswa yang belum pernah meminjam barang sama sekali.
        $eligible = $outstanding->isEmpty();

        return response()->json([
            'borrower' => $this->identity($student, $records->first() ?? $loans->first()),
            'has_loans' => $hasLoans,
            'can_issue_letter' => $eligible,
            'letter_status' => $this->letterStatus($hasLoans, $eligible),
            'eligible' => $eligible,
            'totals' => $this->totals($records, $outstanding, $returned),
            'outstanding' => $outstanding->map(fn (Loan $loan) => $this->formatLoan($loan))->values()->all(),
            'history' => $returned->map(fn (Loan $loan) => $this->formatLoan($loan))->values()->all(),
        ]);
    }

    /**
     * Terbitkan surat bebas laboratorium (PDF berkop surat) dari data peminjaman.
     *
     * Surat diterbitkan untuk peminjam yang tidak memiliki tanggungan, termasuk
     * mahasiswa yang belum pernah meminjam barang sama sekali (surat menerangkan
     * bahwa tidak ada transaksi peminjaman yang tercatat). Hanya identitas yang
     * tidak dikenal sama sekali (tidak ada data mahasiswa maupun transaksi) yang
     * ditolak dengan HTTP 422.
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

        $student = $this->findStudent($studentId, $email, $name);
        $loans = $student !== null
            ? $this->loansOwnedByStudent($student)
            : $this->borrowerLoans($studentId, $email, $name);

        // Peminjam tidak dikenal sama sekali (tidak ada data mahasiswa maupun
        // transaksi).
        if ($student === null && $loans->isEmpty()) {
            throw ValidationException::withMessages([
                'student_id' => 'Data peminjaman peminjam tersebut tidak ditemukan.',
            ]);
        }

        // Transaksi `rejected` tidak dihitung karena peminjam tidak pernah
        // menerima barang; peminjam tanpa transaksi sama sekali tetap dapat
        // memperoleh surat sebagai peminjam tanpa riwayat.
        $records = $loans->whereIn('status', self::ACTIVE_STATUSES)->values();

        $outstanding = $records->whereIn('status', self::OUTSTANDING_STATUSES)->values();
        $returned = $records->where('status', 'returned')->values();
        $borrower = $this->identity($student, $records->first());
        $eligible = $outstanding->isEmpty();
        $letterDate = Carbon::parse($validated['letter_date'] ?? now()->toDateString());
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

        // Surat selalu dapat diunduh selama peminjam memiliki data mahasiswa atau
        // transaksi. Bila masih ada barang yang belum dikembalikan, isi surat
        // otomatis menjadi Surat Keterangan Tanggungan (daftar barang yang belum
        // kembali) agar dokumen tetap sesuai data.
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
            'totals' => $this->totals($records, $outstanding, $returned),
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

    /**
     * Menghasilkan dokumen cetak HTML untuk 1 atau banyak peminjam bebas labor.
     * Hanya peminjam yang bebas labor (tidak memiliki tanggungan) yang dapat
     * dicetak melalui endpoint ini — termasuk mahasiswa yang belum pernah
     * meminjam barang.
     */
    public function printLetters(Request $request)
    {
        $validated = $request->validate([
            'borrowers' => ['required', 'array', 'min:1'],
            'borrowers.*.student_id' => ['nullable', 'string', 'max:50'],
            'borrowers.*.borrower_email' => ['nullable', 'string', 'max:255'],
            'borrowers.*.borrower_name' => ['nullable', 'string', 'max:255'],
            'purpose' => ['required', 'string', 'max:255'],
            'letter_date' => ['nullable', 'date'],
            'laboratory' => ['nullable', 'string', 'max:255'],
            'signatory_name' => ['required', 'string', 'max:255'],
            'signatory_nip' => ['required', 'string', 'max:100'],
        ]);

        $letterDate = Carbon::parse($validated['letter_date'] ?? now()->toDateString());
        $branding = Branding::printableData();
        $letters = [];

        foreach ($validated['borrowers'] as $item) {
            $studentId = trim((string) ($item['student_id'] ?? ''));
            $email = trim((string) ($item['borrower_email'] ?? ''));
            $name = trim((string) ($item['borrower_name'] ?? ''));

            $student = $this->findStudent($studentId, $email, $name);
            $loans = $student !== null
                ? $this->loansOwnedByStudent($student)
                : $this->borrowerLoans($studentId, $email, $name);

            $records = $loans->whereIn('status', self::ACTIVE_STATUSES)->values();
            $outstanding = $records->whereIn('status', self::OUTSTANDING_STATUSES)->values();
            $returned = $records->where('status', 'returned')->values();
            $borrower = $this->identity($student, $records->first());
            $eligible = $outstanding->isEmpty();

            if (! $eligible) {
                $borrowerLabel = $borrower['name'] ?: ($borrower['student_id'] ?: 'Peminjam');
                throw ValidationException::withMessages([
                    'borrowers' => "Peminjam '{$borrowerLabel}' belum memenuhi syarat bebas labor karena masih memiliki tanggungan ({$outstanding->count()} transaksi belum dikembalikan). Cetak masal hanya berlaku untuk peminjam berstatus Bebas Labor.",
                ]);
            }

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

            $letters[] = [
                'branding' => $branding,
                'borrower' => $borrower,
                'loans' => $returned,
                'outstanding' => $outstanding,
                'eligible' => true,
                'purpose' => $validated['purpose'],
                'letterNumber' => $letterNumber,
                'letterDate' => $letterDate->toDateString(),
                'laboratory' => trim((string) ($validated['laboratory'] ?? '')),
                'signatoryName' => $validated['signatory_name'],
                'signatoryNip' => $validated['signatory_nip'],
                'totals' => $this->totals($records, $outstanding, $returned),
            ];
        }

        return response()->view('print.clearance-letters', [
            'letters' => $letters,
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
     * Semua transaksi beserta data barang, dipakai untuk menghitung ringkasan
     * setiap mahasiswa tanpa query berulang.
     *
     * @return Collection<int, Loan>
     */
    private function allLoans(): Collection
    {
        return Loan::query()
            ->with(['item', 'loanItems.item'])
            ->withSum('loanItems', 'qty')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Cocokkan setiap transaksi peminjaman ke data mahasiswa.
     *
     * Banyak transaksi dibuat sebelum data mahasiswa tersedia, sehingga datanya
     * bisa berbeda sebagian. Pencocokan memakai data yang tercatat pada transaksi
     * dengan urutan prioritas: **NIM** (mengikat bila terdaftar pada data
     * mahasiswa), lalu **email**, **nama**, dan **nomor telepon**. Cukup salah
     * satu data tersebut yang cocok, transaksi langsung dihubungkan ke
     * mahasiswanya sehingga tidak hilang dari halaman Bebas Labor.
     *
     * @param  Collection<int, Student>  $students
     * @param  Collection<int, Loan>  $loans
     * @return array{byStudent: array<int, array<int, Loan>>, unowned: Collection<int, Loan>}
     */
    private function attributeLoans(Collection $students, Collection $loans): array
    {
        $byNim = [];
        $byEmail = [];
        $byName = [];
        $byPhone = [];

        foreach ($students as $student) {
            $nim = $this->normalizeText($student->student_id);
            $email = $this->normalizeText($student->email);
            $name = $this->normalizeName($student->name);
            $phone = $this->normalizePhone($student->phone);

            // NIM/email unik pada data mahasiswa. Nama atau nomor telepon bisa
            // sama: yang pertama (urut nama) dipakai agar satu transaksi tidak
            // terhitung pada dua mahasiswa.
            if ($nim !== '' && ! isset($byNim[$nim])) {
                $byNim[$nim] = $student;
            }

            if ($email !== '' && ! isset($byEmail[$email])) {
                $byEmail[$email] = $student;
            }

            if ($name !== '' && ! isset($byName[$name])) {
                $byName[$name] = $student;
            }

            if ($phone !== '' && ! isset($byPhone[$phone])) {
                $byPhone[$phone] = $student;
            }
        }

        $byStudent = [];
        $unowned = collect();

        foreach ($loans as $loan) {
            $owner = $this->matchStudent($loan, $byNim, $byEmail, $byName, $byPhone);

            if ($owner === null) {
                $unowned->push($loan);

                continue;
            }

            $byStudent[$owner->id][] = $loan;
        }

        return [
            'byStudent' => $byStudent,
            'unowned' => $unowned,
        ];
    }

    /**
     * Mahasiswa pemilik satu transaksi, atau `null` bila tidak ada data yang cocok.
     *
     * @param  array<string, Student>  $byNim
     * @param  array<string, Student>  $byEmail
     * @param  array<string, Student>  $byName
     * @param  array<string, Student>  $byPhone
     */
    private function matchStudent(Loan $loan, array $byNim, array $byEmail, array $byName, array $byPhone): ?Student
    {
        $nim = $this->normalizeText($loan->borrower_student_id);

        // NIM pada transaksi mengikat bila terdaftar pada data mahasiswa supaya
        // tidak salah menempel ke mahasiswa lain yang emailnya kebetulan sama.
        if ($nim !== '' && isset($byNim[$nim])) {
            return $byNim[$nim];
        }

        return $byEmail[$this->normalizeText($loan->borrower_email)]
            ?? $byName[$this->normalizeName($loan->borrower_name)]
            ?? $byPhone[$this->normalizePhone($loan->borrower_phone)]
            ?? null;
    }

    /**
     * Semua transaksi yang tercocokkan ke satu mahasiswa (dipakai halaman detail
     * dan penerbitan surat) dengan aturan pencocokan yang sama seperti daftar.
     *
     * @return Collection<int, Loan>
     */
    private function loansOwnedByStudent(Student $student): Collection
    {
        $attribution = $this->attributeLoans(
            Student::query()->orderBy('name')->get(),
            $this->allLoans(),
        );

        return collect($attribution['byStudent'][$student->id] ?? [])->values();
    }

    /**
     * Baris daftar untuk peminjam yang belum terdaftar pada data mahasiswa
     * (peminjaman manual) supaya tanggungannya tetap terpantau.
     *
     * @param  Collection<int, Loan>  $loans
     * @return Collection<int, array<string, mixed>>
     */
    private function unregisteredBorrowerRows(Collection $loans, string $search): Collection
    {
        return $loans
            ->filter(fn (Loan $loan) => $search === '' || $this->searchMatches(
                $search,
                (string) $loan->borrower_student_id,
                (string) $loan->borrower_name,
                (string) $loan->borrower_email,
                (string) $loan->borrower_phone,
            ))
            ->groupBy(fn (Loan $loan) => $this->identityKey($loan))
            ->map(function (Collection $rows) {
                $rows = $rows->values();

                return $this->summarizeBorrower($rows, [
                    'student_id' => $this->firstStudentId($rows),
                    'name' => (string) ($rows->first()?->borrower_name ?? ''),
                    'email' => (string) ($rows->first()?->borrower_email ?? ''),
                    'phone' => (string) ($rows->first()?->borrower_phone ?? ''),
                    'has_student_record' => false,
                ]);
            })
            ->values();
    }

    private function studentMatchesSearch(Student $student, string $search): bool
    {
        return $this->searchMatches(
            $search,
            (string) $student->student_id,
            (string) $student->name,
            (string) $student->email,
            (string) $student->phone,
        );
    }

    private function searchMatches(string $search, string ...$values): bool
    {
        $needle = mb_strtolower($search);

        foreach ($values as $value) {
            if ($value !== '' && str_contains(mb_strtolower($value), $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Identitas satu mahasiswa dari data mahasiswa.
     *
     * @return array<string, mixed>
     */
    private function studentIdentity(Student $student): array
    {
        return [
            'student_id' => trim((string) $student->student_id),
            'name' => trim((string) $student->name),
            'email' => trim((string) $student->email),
            'phone' => trim((string) ($student->phone ?? '')),
            'type' => $student->type,
            'position' => trim((string) ($student->position ?? '')),
            'has_student_record' => true,
        ];
    }

    /**
     * Identitas peminjam untuk halaman detail dan isi surat: data mahasiswa bila
     * terdaftar (sumber sama dengan halaman Data Mahasiswa), selain itu diambil
     * dari transaksi terbaru.
     *
     * @return array<string, mixed>
     */
    private function identity(?Student $student, ?Loan $latest): array
    {
        $studentId = trim((string) ($student?->student_id ?? ''));
        $name = trim((string) ($student?->name ?? ''));
        $email = trim((string) ($student?->email ?? ''));
        $phone = trim((string) ($student?->phone ?? ''));
        $latestStudentId = trim((string) ($latest?->borrower_student_id ?? ''));

        return [
            'name' => $name !== '' ? $name : (string) ($latest?->borrower_name ?? ''),
            'student_id' => $studentId !== '' ? $studentId : $latestStudentId,
            'email' => $email !== '' ? $email : (string) ($latest?->borrower_email ?? ''),
            'phone' => $phone !== '' ? $phone : (string) ($latest?->borrower_phone ?? ''),
            'type' => $student?->type ?? $latest?->borrower_type ?? BorrowerType::MAHASISWA,
            'position' => trim((string) ($student?->position ?? '')),
            'last_loan_at' => $latest?->created_at?->toIso8601String(),
            'has_student_record' => $student !== null,
        ];
    }

    /**
     * Data mahasiswa untuk identitas peminjaman yang sedang diproses.
     * NIM diutamakan; bila tidak ada, email + nama harus sama.
     */
    private function findStudent(string $studentId, string $email, string $name): ?Student
    {
        $normalizedStudentId = $this->normalizeText($studentId);

        if ($normalizedStudentId !== '') {
            $student = Student::query()
                ->whereRaw('LOWER(TRIM(student_id)) = ?', [$normalizedStudentId])
                ->first();

            if ($student !== null) {
                return $student;
            }
        }

        $normalizedEmail = $this->normalizeText($email);

        if ($normalizedEmail === '') {
            return null;
        }

        $normalizedName = $this->normalizeName($name);

        return Student::query()
            ->whereRaw('LOWER(TRIM(email)) = ?', [$normalizedEmail])
            ->get()
            ->first(fn (Student $student) => $normalizedName === '' || $this->normalizeName($student->name) === $normalizedName);
    }

    /**
     * Status kelayakan surat. `belum_pernah_meminjam` sengaja tetap dibedakan
     * agar halaman dapat menandai peminjam tanpa riwayat, tetapi peminjam
     * tersebut tetap `eligible` dan suratnya dapat diterbitkan.
     */
    private function letterStatus(bool $hasLoans, bool $eligible): string
    {
        if (! $hasLoans) {
            return self::LETTER_NONE;
        }

        return $eligible ? self::LETTER_BEBAS_LABOR : self::LETTER_TANGGUNGAN;
    }

    /**
     * Nomor telepon tanpa pemisah agar varian penulisan (spasi, tanda hubung,
     * atau awalan +62) tetap dianggap sama.
     */
    private function normalizePhone(?string $value): string
    {
        $digits = (string) preg_replace('/\D+/', '', (string) $value);

        if (str_starts_with($digits, '62')) {
            return '0' . substr($digits, 2);
        }

        return $digits;
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
     * Ringkasan satu peminjam untuk daftar "Bebas Labor".
     *
     * `has_loans` bernilai benar hanya bila peminjam pernah meminjam barang
     * (transaksi `rejected` tidak dihitung). Surat bebas labor diberikan bila
     * tidak ada barang yang belum kembali — termasuk untuk peminjam yang belum
     * pernah meminjam barang sama sekali (`belum_pernah_meminjam`).
     *
     * @param  Collection<int, Loan>  $rows
     * @param  array<string, mixed>  $identity
     */
    private function summarizeBorrower(Collection $rows, array $identity): array
    {
        $records = $rows->whereIn('status', self::ACTIVE_STATUSES)->values();
        $outstanding = $records->whereIn('status', self::OUTSTANDING_STATUSES)->values();
        $returned = $records->where('status', 'returned')->values();
        $latest = $records->first();
        $hasLoans = $records->isNotEmpty();
        // Belum pernah meminjam berarti tidak ada tanggungan: tetap bebas labor
        // dan suratnya dapat diterbitkan dengan keterangan tanpa riwayat.
        $eligible = $outstanding->isEmpty();

        return [
            'student_id' => $identity['student_id'] ?? '',
            'name' => $identity['name'] ?? '',
            'email' => $identity['email'] ?? '',
            'phone' => $identity['phone'] ?? '',
            // Jenis peminjam ikut dibawa agar halaman bebas labor bisa
            // menampilkan badge yang sama dengan halaman Data Peminjam.
            'type' => $identity['type'] ?? BorrowerType::MAHASISWA,
            'position' => $identity['position'] ?? '',
            'has_student_record' => (bool) ($identity['has_student_record'] ?? false),
            'has_loans' => $hasLoans,
            'can_issue_letter' => $eligible,
            'letter_status' => $this->letterStatus($hasLoans, $eligible),
            'total_loans' => $records->count(),
            'returned_loans' => $returned->count(),
            'outstanding_loans' => $outstanding->count(),
            'total_qty' => (int) $records->sum(fn (Loan $loan) => $this->quantity($loan)),
            'outstanding_qty' => (int) $outstanding->sum(fn (Loan $loan) => $this->quantity($loan)),
            'last_loan_at' => $latest?->created_at?->toIso8601String(),
            'eligible' => $eligible,
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
