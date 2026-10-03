<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Student;
use App\Support\BorrowerType;
use App\Support\SheetsWebhook;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\IOFactory;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));

        // `type` boleh berisi beberapa jenis dipisah koma (mis.
        // "tendik,dosen,umum"). Dipakai halaman Data Tendik/Dosen yang tab
        // "Semua"-nya hanya boleh memuat kelompok halaman itu, bukan seluruh
        // peminjam — tanpa ini data mahasiswa ikut tampil di halaman tersebut.
        $types = $this->parseTypes($validated['type'] ?? null);

        if ($types === null) {
            return response()->json([
                'message' => 'Jenis peminjam tidak dikenal. Pilihan: '.implode(', ', BorrowerType::ALL).'.',
                'errors' => ['type' => ['Jenis peminjam tidak dikenal.']],
            ], 422);
        }

        $query = Student::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('student_id', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('role', 'like', "%{$search}%")
                        ->orWhere('position', 'like', "%{$search}%");
                });
            })
            ->when($types !== [], fn ($query) => $query->whereIn('type', $types))
            ->orderBy('name');

        // Jumlah per jenis untuk badge pada tab filter. Sengaja dihitung
        // terpisah dari filter `type` supaya angka jenis lain tetap terlihat
        // meski sedang menampilkan satu jenis saja. Kata kunci pencarian tetap
        // ikut dipakai agar angka pada tab cocok dengan yang diketik petugas.
        $counts = Student::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('student_id', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('role', 'like', "%{$search}%")
                        ->orWhere('position', 'like', "%{$search}%");
                });
            })
            ->selectRaw('type, count(*) as aggregate')
            ->groupBy('type')
            ->pluck('aggregate', 'type');
        $byType = ['all' => (int) $counts->sum()];
        foreach (BorrowerType::ALL as $option) {
            $byType[$option] = (int) ($counts[$option] ?? 0);
        }

        // Jumlah untuk kelompok yang sedang ditampilkan halaman ini. Dipakai
        // badge tab "Semua" pada halaman Data Tendik/Dosen agar angkanya tidak
        // menghitung kelompok di luar halaman tersebut.
        $scopeTotal = $types === []
            ? $byType['all']
            : array_sum(array_intersect_key($byType, array_flip($types)));

        $metaType = $types === [] ? null : implode(',', $types);

        // Tanpa `per_page` seluruh data dikembalikan. Pemanggil lama (mis.
        // form peminjaman yang butuh semua peminjam dalam satu daftar)
        // tetap bekerja apa adanya; halaman tabel peminjam mengirim
        // `per_page` sehingga tabel cukup 10 baris per halaman.
        if (! array_key_exists('per_page', $validated) || $validated['per_page'] === null) {
            $students = $query->get();

            return response()->json([
                'data' => $students,
                'meta' => [
                    'total' => $students->count(),
                    'search' => $search,
                    'type' => $metaType,
                    'by_type' => $byType,
                    'scope_total' => $scopeTotal,
                ],
            ]);
        }

        $paginator = $query->paginate(
            (int) $validated['per_page'],
            ['*'],
            'page',
            (int) ($validated['page'] ?? 1),
        );

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'total' => $paginator->total(),
                'search' => $search,
                'type' => $metaType,
                'by_type' => $byType,
                'scope_total' => $scopeTotal,
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
            ],
        ]);
    }

    /**
     * Baca parameter `type` yang boleh berisi satu atau beberapa jenis
     * dipisah koma.
     *
     * @return array<int, string>|null Daftar jenis yang sah, array kosong bila
     *                                 parameter tidak dikirim (berarti semua
     *                                 jenis), atau null bila ada jenis yang
     *                                 tidak dikenal.
     */
    private function parseTypes(?string $raw): ?array
    {
        $value = trim((string) $raw);

        if ($value === '') {
            return [];
        }

        $types = [];

        foreach (explode(',', $value) as $part) {
            $type = BorrowerType::fromText($part);

            // Jenis tak dikenal ditolak eksplisit: lebih baik gagal jelas
            // daripada menampilkan daftar yang tidak diminta.
            if ($type === null) {
                return null;
            }

            $types[$type] = true;
        }

        // Urutkan mengikuti urutan resmi supaya query selalu sama.
        return array_values(array_filter(
            BorrowerType::ALL,
            fn (string $type) => isset($types[$type]),
        ));
    }

    public function store(Request $request)
    {
        $student = Student::create($this->validatedData($request));

        return response()->json([
            'message' => 'Data mahasiswa berhasil ditambahkan.',
            'student' => $student,
        ], 201);
    }

    public function update(Request $request, Student $student)
    {
        $student->update($this->validatedData($request, $student));
        $student->refresh();

        // Perubahan data peminjam ikut ditulis balik ke spreadsheet bila
        // kelompok ini punya webhook Apps Script terpasang.
        // Kegagalan tidak membatalkan penyimpanan lokal: spreadsheet adalah
        // pelengkap, bukan sumber kebenaran.
        $push = $this->pushToSpreadsheet($student);

        return response()->json([
            'message' => 'Data peminjam berhasil diperbarui.'
                .($push['skipped'] ?? false ? '' : ' '.$push['message']),
            'student' => $student,
            'spreadsheet' => $push,
        ]);
    }

    /**
     * Kirim data peminjam ke spreadsheet lewat webhook, lalu catat waktu
     * keberhasilannya.
     *
     * @return array{ok: bool, skipped: bool, message: string}
     */
    private function pushToSpreadsheet(Student $student): array
    {
        $webhookKey = BorrowerType::webhookKey($student->type);

        if ($webhookKey === null) {
            return ['ok' => false, 'skipped' => true, 'message' => ''];
        }

        $result = SheetsWebhook::push($student, BorrowerType::webhookUrl($student->type));

        if ($result['ok']) {
            $lastKey = BorrowerType::webhookLastKey($student->type);

            if ($lastKey !== null) {
                AppSetting::setValue($lastKey, now()->toIso8601String());
            }
        }

        return [
            'ok' => $result['ok'],
            'skipped' => (bool) ($result['skipped'] ?? false),
            // Kegagalan disembunyikan dari pesan utama supaya petugas tidak
            // mengira datanya gagal disimpan, tapi tetap dikembalikan agar UI
            // bisa memberi tahu bahwa spreadsheet belum ikut ter-update.
            'message' => $result['ok']
                ? 'Perubahan data juga dikirim ke spreadsheet.'
                : 'Tersimpan di aplikasi, tetapi gagal dikirim ke spreadsheet: '.($result['message'] ?? 'tidak diketahui'),
        ];
    }

    public function destroy(Student $student)
    {
        $student->delete();

        return response()->json(['message' => 'Data peminjam berhasil dihapus.']);
    }

    /**
     * Baca URL spreadsheet & waktu sinkron terakhir untuk sebuah jenis
     * peminjam.
     *
     * Tanpa parameter `type` (pemanggil lama) yang dibaca adalah
     * spreadsheet mahasiswa, sehingga konfigurasi yang sudah tersimpan tetap
     * dipakai apa adanya.
     */
    public function importSource(Request $request)
    {
        $type = $this->resolveSpreadsheetType($request);
        $urlKey = BorrowerType::syncUrlKey($type);

        if ($urlKey === null) {
            return response()->json([
                'type' => $type,
                'supported' => false,
                'url' => '',
                'last_synced_at' => null,
                'message' => 'Jenis peminjam ini tidak memakai sinkronisasi spreadsheet.',
            ]);
        }

        return response()->json([
            'type' => $type,
            'supported' => true,
            // Tendik & Dosen membaca satu spreadsheet yang sama, jadi URL-nya
            // juga sama. `syncUrl()` otomatis memakai URL lama per jenis sebagai
            // cadangan bila kunci gabungan masih kosong.
            'url' => BorrowerType::syncUrl($type),
            // Waktu sinkronisasi otomatis terakhir yang berhasil, agar halaman
            // Data Peminjam dapat menampilkan "sinkron terakhir" walaupun
            // halaman baru dibuka/dimuat ulang.
            'last_synced_at' => AppSetting::getValue(BorrowerType::syncLastKey($type)),
            // Webhook tulis-balik: kalau terisi, Jabatan / Unit Kerja yang
            // diubah di aplikasi ikut dikirim ke spreadsheet.
            'webhook_url' => BorrowerType::webhookUrl($type),
            'webhook_last_pushed_at' => AppSetting::getValue(BorrowerType::webhookLastKey($type)),
        ]);
    }

    /**
     * Simpan URL webhook Google Apps Script untuk menulis balik ke spreadsheet.
     *
     * URL kosong menghapus webhook (kembali ke sinkronisasi satu arah).
     * Hanya host resmi Google Apps Script yang diterima agar nilai ini tidak
     * bisa dipakai memanggil alamat lain dari server.
     */
    public function saveWebhook(Request $request)
    {
        $validated = $request->validate([
            // `nullable` membuat string kosong berubah jadi null, sehingga
            // string kosong (mematikan webhook) dibaca lewat `has()` agar
            // tidak tertukar dengan "parameter tidak dikirim".
            'url' => ['nullable', 'string', 'max:2000'],
            'type' => ['nullable', 'string', Rule::in(BorrowerType::SPREADSHEET_TYPES)],
        ]);

        $type = BorrowerType::clean($validated['type'] ?? null);
        $key = BorrowerType::webhookKey($type);

        if ($key === null) {
            return response()->json([
                'message' => 'Jenis peminjam ini tidak memakai sinkronisasi spreadsheet.',
            ], 422);
        }

        $raw = $request->input('url');
        $url = SheetsWebhook::cleanUrl(is_string($raw) ? $raw : '');

        if ($url === '') {
            AppSetting::setValue($key, null);

            return response()->json([
                'message' => 'Webhook ditulis balik dimatikan untuk '.BorrowerType::label($type).'.',
                'type' => $type,
                'webhook_url' => '',
            ]);
        }

        if (! SheetsWebhook::isValidUrl($url)) {
            return response()->json([
                'message' => 'URL webhook tidak dikenali. Tempel URL Web App Google Apps Script '
                    .'(https://script.google.com/macros/s/.../exec).',
            ], 422);
        }

        AppSetting::setValue($key, $url);

        return response()->json([
            'message' => 'Webhook spreadsheet untuk '.BorrowerType::label($type).' berhasil disimpan.',
            'type' => $type,
            'webhook_url' => $url,
        ]);
    }

    /**
     * Uji webhook dengan mengirim satu baris contoh.
     *
     * Memakai data peminjam yang benar-benar ada bila ada, supaya hasil uji
     * mencerminkan isi spreadsheet. Bila belum ada data sama sekali, tetap
     * dikembalikan "ok"=false dengan pesan jelas, bukan error 500.
     */
    public function testWebhook(Request $request)
    {
        $validated = $request->validate([
            'type' => ['nullable', 'string', Rule::in(BorrowerType::SPREADSHEET_TYPES)],
        ]);

        $type = BorrowerType::clean($validated['type'] ?? null);
        $url = BorrowerType::webhookUrl($type);

        if ($url === '' || ! SheetsWebhook::isValidUrl($url)) {
            return response()->json([
                'ok' => false,
                'message' => 'Webhook belum dikonfigurasi untuk '.BorrowerType::label($type).'.',
            ], 422);
        }

        $student = Student::where('type', $type)->first();

        if ($student === null) {
            return response()->json([
                'ok' => false,
                'message' => 'Belum ada data '.BorrowerType::label($type).' untuk diuji. '
                    .'Tambahkan satu data lebih dulu, lalu coba lagi.',
            ], 422);
        }

        $result = SheetsWebhook::push($student, $url);

        if ($result['ok']) {
            $lastKey = BorrowerType::webhookLastKey($type);

            if ($lastKey !== null) {
                AppSetting::setValue($lastKey, now()->toIso8601String());
            }
        }

        return response()->json([
            'ok' => $result['ok'],
            'message' => $result['ok']
                ? 'Berhasil menulis ke spreadsheet. Baris contoh: '.$student->name
                    .' ('.($student->student_id ?: $student->email).').'
                : ($result['message'] ?? 'Gagal menghubungi spreadsheet.'),
        ], $result['ok'] ? 200 : 422);
    }

    public function saveImportSource(Request $request)
    {
        $validated = $request->validate([
            'url' => ['required', 'url', 'max:2000'],
            'type' => ['nullable', 'string', Rule::in(BorrowerType::SPREADSHEET_TYPES)],
        ]);

        $type = BorrowerType::clean($validated['type'] ?? null);
        $urlKey = BorrowerType::syncUrlKey($type);

        if ($urlKey === null) {
            return response()->json([
                'message' => 'Jenis peminjam ini tidak memakai sinkronisasi spreadsheet.',
            ], 422);
        }

        AppSetting::setValue($urlKey, $validated['url']);

        return response()->json([
            'message' => 'URL spreadsheet berhasil disimpan.',
            'type' => $type,
            'url' => $validated['url'],
        ]);
    }

    /**
     * Impor data dari file spreadsheet (CSV hasil unduhan Google Sheets /
     * Excel). Baris yang cocok berdasarkan NIM/NIP atau email akan diperbarui;
     * baris baru akan ditambahkan.
     *
     * Untuk spreadsheet Tendik & Dosen, Role per baris menentukan peran;
     * NIP opsional dan email menjadi kunci pencocokan.
     */
    public function import(Request $request)
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:5120', 'mimes:csv,txt,xlsx,xls'],
            'type' => ['nullable', 'string', Rule::in(BorrowerType::ALL)],
        ]);

        $rows = $this->readRows($request->file('file'));

        if ($rows === null) {
            return response()->json([
                'message' => 'File tidak dapat dibaca. Gunakan format CSV, XLSX, atau XLS.',
            ], 422);
        }

        $type = $validated['type'] ?? null;

        return $this->importRows(
            $rows,
            $type === null ? null : BorrowerType::forcedType($type),
            $type !== null && BorrowerType::usesSharedSheet($type),
        );
    }

    /**
     * Ambil CSV Google Sheets yang sudah dipublikasikan untuk penggunaan web.
     * Tidak menggunakan Google Sheets API atau API key.
     *
     * Parameter `type` menentukan spreadsheet yang diambil. Pada spreadsheet
     * pegawai bersama, Role menentukan kategori dosen atau tendik.
     */
    public function importFromPublishedCsv(Request $request)
    {
        $validated = $request->validate([
            'url' => ['required', 'url', 'max:2000'],
            'type' => ['nullable', 'string', Rule::in(BorrowerType::SPREADSHEET_TYPES)],
        ]);

        $type = BorrowerType::clean($validated['type'] ?? null);

        try {
            $response = Http::timeout(20)
                ->withHeaders(['Cache-Control' => 'no-cache'])
                ->get($this->publishedCsvUrl($validated['url']));
        } catch (\Throwable) {
            return response()->json([
                'message' => 'CSV Google Sheets tidak dapat dihubungi.',
            ], 422);
        }

        if ($response->failed()) {
            return response()->json([
                'message' => "Google Sheets mengembalikan HTTP {$response->status()}. Pastikan sheet sudah dipublikasikan sebagai CSV.",
            ], 422);
        }

        $rows = $this->readCsvContent($response->body());

        if ($rows === null) {
            return response()->json([
                'message' => 'Respons Google Sheets bukan CSV yang valid.',
            ], 422);
        }

        $result = $this->importRows(
            $rows,
            BorrowerType::forcedType($type),
            BorrowerType::usesSharedSheet($type),
        );

        if ($result->getStatusCode() < 300) {
            // URL & waktu sinkron disimpan pada kunci kelompoknya, sehingga
            // Tendik & Dosen berbagi satu kunci sedangkan mahasiswa terpisah.
            $urlKey = BorrowerType::syncUrlKey($type);

            if ($urlKey !== null) {
                AppSetting::setValue($urlKey, $validated['url']);
                AppSetting::setValue(BorrowerType::syncLastKey($type), now()->toIso8601String());
            }
        }

        return $result;
    }

    /**
     * Tentukan jenis peminjam dari parameter `type`.
     *
     * Parameter kosong/tidak dikenal berarti mahasiswa supaya pemanggil lama
     * yang tidak mengirim `type` tetap memakai spreadsheet mahasiswa.
     */
    private function resolveSpreadsheetType(Request $request): string
    {
        $raw = trim((string) $request->query('type', ''));

        if ($raw === '') {
            return BorrowerType::MAHASISWA;
        }

        $type = BorrowerType::fromText($raw);

        return $type ?? BorrowerType::MAHASISWA;
    }

    /**
     * @param  string|null  $forcedType  Bila diisi, seluruh baris dipaksa
     *                                   menjadi jenis tersebut dan kolom
     *                                   Role di spreadsheet diabaikan.
     *                                   Dipakai untuk spreadsheet mahasiswa.
     *
     *                                   Untuk spreadsheet gabungan Tendik &
     *                                   Dosen nilainya null sehingga Role
     *                                   dibaca per baris.
     */
    private function importRows(array $rows, ?string $forcedType = null, bool $sharedSheet = false)
    {
        // Kategori internal Tendik/Dosen untuk spreadsheet pegawai bersama.
        // Kosong pada impor berkas yang tidak terikat ke spreadsheet tersebut.
        $sharedTypes = $sharedSheet ? BorrowerType::SHARED_SHEET_TYPES : [];

        // Cari baris header di dalam file: template berisi baris judul &
        // petunjuk di atasnya, sedangkan CSV biasa langsung ber-header.
        $columnMap = null;
        $headerIndex = null;

        foreach ($rows as $index => $row) {
            $map = $this->mapColumns($row);

            if ($map !== null) {
                $columnMap = $map;
                $headerIndex = $index;

                break;
            }
        }

        if ($columnMap === null) {
            return response()->json([
                'message' => 'Header spreadsheet tidak dikenali. Gunakan Nama dan Email, serta NIM/NIP untuk mahasiswa atau Role untuk spreadsheet pegawai.',
            ], 422);
        }

        $imported = 0;
        $updated = 0;
        $unchanged = 0;
        $errors = [];
        $seenStudentIds = [];
        $seenEmails = [];
        $rowNumber = $headerIndex + 1;

        foreach (array_slice($rows, $headerIndex + 1) as $raw) {
            $rowNumber++;

            // Lewati baris yang benar-benar kosong.
            if (implode('', (array) $raw) === '') {
                continue;
            }

            $row = $this->normalizeRow((array) $raw);
            $data = [
                'student_id' => isset($columnMap['student_id'])
                    ? trim((string) ($row[$columnMap['student_id']] ?? ''))
                    : '',
                'name' => trim((string) ($row[$columnMap['name']] ?? '')),
                'email' => strtolower(trim((string) ($row[$columnMap['email']] ?? ''))),
                'phone' => isset($columnMap['phone']) ? trim((string) ($row[$columnMap['phone']] ?? '')) : '',
                'role' => isset($columnMap['role']) ? trim((string) ($row[$columnMap['role']] ?? '')) : '',
                'position' => isset($columnMap['position']) ? trim((string) ($row[$columnMap['position']] ?? '')) : '',
            ];

            // Kategori pegawai ditentukan dari Jabatan / Unit Kerja yang
            // ditampilkan di bawah nama, bukan dari kolom Role.
            $rawRole = $data['role'];
            $type = $forcedType !== null
                ? BorrowerType::clean($forcedType)
                : ($sharedSheet
                    ? (in_array(BorrowerType::fromText($rawRole), [BorrowerType::MAHASISWA, BorrowerType::UMUM], true)
                        ? null
                        : $this->employeeTypeFromPosition($data['position']))
                    : ($rawRole === '' ? BorrowerType::MAHASISWA : BorrowerType::fromText($rawRole)));

            if ($data['student_id'] === '' && $data['name'] === '' && $data['email'] === '') {
                continue;
            }

            // Jenis tak dikenal ditolak, bukan diam-diam disimpan sebagai
            // mahasiswa supaya salah kategori tidak sulit ditelusuri.
            if (! $sharedSheet && $forcedType === null && $rawRole !== '' && $type === null) {
                $errors[] = "Baris {$rowNumber}: Jenis peminjam \"{$rawRole}\" tidak dikenal "
                    .'(pilihan: '.implode(', ', BorrowerType::options()).').';

                continue;
            }

            // Spreadsheet gabungan hanya menerima kategori pegawai.
            if ($sharedTypes !== [] && ! in_array($type, $sharedTypes, true)) {
                $errors[] = "Baris {$rowNumber}: Role \"{$rawRole}\" tidak boleh ada di spreadsheet Tendik & Dosen.";

                continue;
            }

            $data['type'] = $type;
            $data['student_id'] = $data['student_id'] !== '' ? $data['student_id'] : null;

            if ($data['student_id'] !== null && isset($seenStudentIds[$data['student_id']])) {
                $errors[] = "Baris {$rowNumber}: NIM {$data['student_id']} duplikat di dalam file.";

                continue;
            }

            if (isset($seenEmails[$data['email']])) {
                $errors[] = "Baris {$rowNumber}: Email {$data['email']} duplikat di dalam file.";

                continue;
            }

            $validator = Validator::make($data, [
                'student_id' => in_array($data['type'], BorrowerType::SHARED_SHEET_TYPES, true)
                    ? ['nullable', 'string', 'max:50']
                    : ['required', 'string', 'max:50'],
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255'],
                'phone' => ['nullable', 'string', 'max:20'],
                'type' => ['required', 'string', Rule::in(BorrowerType::ALL)],
                'role' => ['nullable', 'string', 'max:150'],
                'position' => ['nullable', 'string', 'max:150'],
            ]);

            if ($validator->fails()) {
                $errors[] = "Baris {$rowNumber}: ".implode(' ', $validator->errors()->all());

                continue;
            }

            // Cocokkan baris spreadsheet dengan data yang sudah tersimpan:
            // NIM dipakai sebagai kunci utama, email sebagai kunci cadangan.
            // Dengan begitu perbaikan NIM pada spreadsheet ikut terpakai
            // (bukan ditolak sebagai bentrok) sebagaimana perubahan data lain.
            $byStudentId = $data['student_id'] === null
                ? null
                : Student::where('student_id', $data['student_id'])->first();
            $byEmail = Student::where('email', $data['email'])->first();

            // Email/NIM milik dua mahasiswa berbeda = bentrok sungguhan.
            if ($byStudentId && $byEmail && $byStudentId->id !== $byEmail->id) {
                $errors[] = "Baris {$rowNumber}: Email {$data['email']} sudah dipakai NIM {$byEmail->student_id}.";

                continue;
            }

            if ($data['student_id'] !== null) {
                $seenStudentIds[$data['student_id']] = true;
            }
            $seenEmails[$data['email']] = true;

            $existing = $byStudentId ?? $byEmail;
            $payload = [
                'student_id' => $data['student_id'],
                'name' => $data['name'],
                'type' => $data['type'],
                'role' => $data['role'] !== '' ? $data['role'] : null,
                'position' => $data['position'] !== '' ? $data['position'] : null,
                'email' => $data['email'],
                'phone' => $data['phone'] !== '' ? $data['phone'] : null,
            ];

            if (! $existing) {
                Student::create($payload);
                $imported++;

                continue;
            }

            $existing->fill($payload);

            // Sinkronisasi otomatis menulis ulang seluruh isi spreadsheet
            // secara berkala. Baris yang isinya sama tidak perlu disimpan dan
            // tidak dihitung sebagai perubahan, sehingga angka "diperbarui"
            // benar-benar hanya berisi baris yang berubah di spreadsheet.
            if (! $existing->isDirty()) {
                $unchanged++;

                continue;
            }

            $existing->save();
            $updated++;
        }

        $syncedAt = now()->toIso8601String();

        if ($imported === 0 && $updated === 0 && $unchanged === 0) {
            return response()->json([
                'message' => 'Tidak ada data yang diimpor. Periksa kembali isi file.',
                'ok' => false,
                'imported' => 0,
                'updated' => 0,
                'unchanged' => 0,
                'synced_at' => null,
                'errors' => $errors,
            ], 422);
        }

        $summary = ["{$imported} peminjam baru ditambahkan", "{$updated} diperbarui"];

        if ($unchanged > 0) {
            $summary[] = "{$unchanged} tanpa perubahan";
        }

        return response()->json([
            'ok' => true,
            'message' => 'Impor selesai: '.implode(', ', $summary).'.'
                .(count($errors) > 0 ? ' '.count($errors).' baris dilewati.' : ''),
            'imported' => $imported,
            'updated' => $updated,
            'unchanged' => $unchanged,
            'synced_at' => $syncedAt,
            'errors' => $errors,
        ]);
    }

    private function validatedData(Request $request, ?Student $student = null): array
    {
        $studentIdRule = 'unique:students,student_id';
        $emailRule = 'unique:students,email';

        if ($student) {
            $studentIdRule .= ','.$student->id;
            $emailRule .= ','.$student->id;
        }

        $validated = $request->validate([
            'student_id' => [
                in_array($request->input('type', $student?->type), BorrowerType::SHARED_SHEET_TYPES, true)
                    ? 'nullable'
                    : 'required',
                'string',
                'max:50',
                $studentIdRule,
            ],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['sometimes', 'string', Rule::in(BorrowerType::ALL)],
            'role' => ['nullable', 'string', 'max:150'],
            'position' => ['nullable', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', $emailRule],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        // Pemanggil lama tidak mengirim `type`; defaults ke mahasiswa supaya
        // data lama tidak berubah jenis hanya karena disimpan ulang.
        $validated['role'] = $validated['role'] ?? ($student?->role ?? null);
        $validated['position'] = $validated['position'] ?? ($student?->position ?? null);
        $validated['type'] = $validated['type'] ?? ($student?->type ?? BorrowerType::MAHASISWA);

        if (in_array($validated['type'], BorrowerType::SHARED_SHEET_TYPES, true)) {
            $validated['type'] = BorrowerType::employeeTypeFromPosition($validated['position']);
        }

        return $validated;
    }

    /**
     * Unduh template impor untuk spreadsheet peminjam.
     *
     * File .xls (HTML table) berisi judul, petunjuk, dan header berformat
     * TANPA data contoh — supaya contoh tidak ikut terimpor saat petugas lupa
     * menghapus baris percontohan. Bisa langsung dibuka di Excel atau diunggah
     * ke Google Sheets.
     *
     * Mahasiswa memakai template khusus. Tendik & Dosen memakai satu template
     * gabungan dengan Role bebas sebagai pembeda peran; spreadsheet mahasiswa
     * tidak pernah ikut disentuh.
     */
    public function downloadTemplate(Request $request)
    {
        $type = $this->resolveSpreadsheetType($request);
        $shared = BorrowerType::usesSharedSheet($type);
        $label = $shared ? 'Tendik & Dosen' : BorrowerType::label($type);
        $heading = Str::upper($label);
        $identity = $shared ? 'NIP (opsional)' : $this->identityColumnLabel($type);

        // Petunjuk per kelompok: yang relevan saja yang ditampilkan.
        $notes = match (true) {
            $shared => [
                'Kolom '.$this->strong('Role').' opsional dan tidak menentukan kategori. Kategori Dosen ditentukan jika '
                    .$this->strong('Jabatan / Unit Kerja').' memuat kata '.$this->quote('Dosen').'; selain itu masuk Tendik.',
                'Role dapat berisi peran seperti '.$this->quote('Dosen').', '.$this->quote('Tendik')
                    .', atau '.$this->quote('Rumah Tangga').'.',
                'Kolom '.$this->strong('NIP (opsional)').' boleh dikosongkan. Gunakan email untuk mengenali data.',
                'Isi kolom '.$this->strong('Jabatan / Unit Kerja').' dengan tugas atau unit kerja, misalnya '
                    .$this->quote('Staf Bagian Keuangan').' atau '.$this->quote('Dosen Teknik Informatika').'.',
            ],
            $type === BorrowerType::TENDIK => [
                'Gunakan '.$this->strong('NIP').' pegawai, bukan NIM mahasiswa.',
                'Isi kolom '.$this->strong('Jabatan / Unit Kerja').' dengan jabatan resmi, misalnya '
                    .$this->quote('Staf Bagian Keuangan').' atau '.$this->quote('Asisten Lab Komputer').'.',
            ],
            $type === BorrowerType::DOSEN => [
                'Gunakan '.$this->strong('NIP').' dosen, bukan NIM mahasiswa.',
                'Isi kolom '.$this->strong('Jabatan / Unit Kerja').' dengan jabatan atau program studi, misalnya '
                    .$this->quote('Dosen Teknik Informatika').'.',
            ],
            default => [
                'Gunakan '.$this->strong('NIM').' mahasiswa.',
                'Kolom '.$this->strong('Jabatan / Unit Kerja').' dapat diisi dengan nama program studi (opsional).',
            ],
        };

        $noteCount = count($notes);
        $headerRow = 7 + $noteCount - ($shared ? 1 : 0);
        $firstDataRow = $headerRow + 2;

        // Kolom "Role" hanya ada pada template gabungan.
        $colSpan = $shared ? 6 : 5;
        $typeNoteRow = $shared
            ? ''
            : '<tr><td colspan="'.$colSpan.'" style="font-size:10pt; color:#475569; padding:1px 4px;">'
                .($noteCount + 2).'. Seluruh data pada templat ini akan dikategorikan sebagai '
                .$this->strong($label).'. Kolom Role tidak perlu ditambahkan.</td></tr>';

        $noteRows = '';
        foreach ($notes as $index => $note) {
            $noteRows .= '<tr>'
                .'<td colspan="'.$colSpan.'" style="font-size:10pt; color:#475569; padding:1px 4px;">'
                .($index + 1).'. '.$note
                .'</td></tr>';
        }

        $fillNote = $noteCount + 1;

        $headerCell = 'background-color:#0e7490; color:#ffffff; font-weight:bold; '
            .'border:1px solid #155e75; padding:8px 10px; white-space:nowrap;';
        $typeHeaderCell = $shared
            ? '<td style="'.$headerCell.'">Role</td>'
            : '';

        $spacer = '<tr><td colspan="'.$colSpan.'" style="font-size:1pt; line-height:1pt;">&nbsp;</td></tr>';

        $html = <<<HTML
            <html xmlns:x="urn:schemas-microsoft-com:office:excel">
            <head>
                <meta charset="UTF-8">
                <!--[if gte mso 9]><xml>
                    <x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>
                        <x:Name>Data {$label}</x:Name>
                        <x:WorksheetOptions><x:Panes></x:Panes></x:WorksheetOptions>
                    </x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook>
                </xml><![endif]-->
            </head>
            <body>
                <table border="0" cellspacing="0" cellpadding="0">
                    <colgroup>
                        <col style="width:150px;">
                        <col style="width:220px;">
                        <col style="width:240px;">
                        <col style="width:240px;">
                        <col style="width:130px;">
                        <col style="width:120px;">
                    </colgroup>
                    <tr><td colspan="{$colSpan}" style="font-size:14pt; font-weight:bold; color:#0e7490; padding:0 4px 2px;">TEMPLATE IMPOR DATA {$heading}</td></tr>
                    {$spacer}
                    <tr><td colspan="{$colSpan}" style="font-size:11pt; font-weight:bold; color:#0f172a; padding:0 4px 2px;">PANDUAN PENGISIAN</td></tr>
                    {$noteRows}
                    <tr><td colspan="{$colSpan}" style="font-size:10pt; color:#475569; padding:1px 4px;">{$fillNote}. Masukkan data mulai baris {$firstDataRow}. Jangan mengubah atau menghapus judul, panduan, maupun header kolom.</td></tr>
                    {$typeNoteRow}
                    {$spacer}
                    <tr>
                        {$typeHeaderCell}
                        <td style="{$headerCell}">{$identity}</td>
                        <td style="{$headerCell}">Nama</td>
                        <td style="{$headerCell}">Jabatan / Unit Kerja</td>
                        <td style="{$headerCell}">Email</td>
                        <td style="{$headerCell}">No. Telepon</td>
                    </tr>
                    {$spacer}
                </table>
            </body>
            </html>
            HTML;

        return response($html)
            ->header('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="template-impor-'.($shared ? 'tendik-dosen' : $type).'.xls"');
    }

    /** Tebalkan teks pada template HTML (dipakai berulang agar rapi). */
    private function strong(string $text): string
    {
        return '<b style="color:#0f172a;">'.$text.'</b>';
    }

    /** Tampilkan contoh nilai sebagai kutipan pada petunjuk template. */
    private function quote(string $text): string
    {
        return '&ldquo;'.$text.'&rdquo;';
    }

    /**
     * Nama kolom identitas pada template, mengikuti kelompok peminjam.
     */
    private function identityColumnLabel(string $type): string
    {
        return match (BorrowerType::clean($type)) {
            BorrowerType::TENDIK, BorrowerType::DOSEN => 'NIP',
            BorrowerType::UMUM => 'Nomor Identitas',
            default => 'NIM',
        };
    }

    /**
     * Baca seluruh baris file impor. CSV/TXT dibaca dengan fgetcsv,
     * sedangkan XLSX/XLS dibaca dengan PhpSpreadsheet.
     * Mengembalikan null bila file tidak dapat dibaca.
     */
    private function readRows(UploadedFile $file): ?array
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());

        if (in_array($extension, ['csv', 'txt'], true)) {
            return $this->readCsvRows($file->getRealPath());
        }

        return $this->readSpreadsheetRows($file->getRealPath());
    }

    private function readCsvRows(string $path): ?array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return null;
        }

        $rows = [];
        while (($raw = fgetcsv($handle)) !== false) {
            $rows[] = $this->normalizeRow((array) $raw);
        }

        fclose($handle);

        return $rows;
    }

    private function readCsvContent(string $content): ?array
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false || fwrite($handle, $content) === false || ! rewind($handle)) {
            return null;
        }

        $rows = [];
        while (($raw = fgetcsv($handle)) !== false) {
            $rows[] = $this->normalizeRow((array) $raw);
        }

        fclose($handle);

        return $rows;
    }

    private function publishedCsvUrl(string $url): string
    {
        $parts = parse_url($url);

        if (($parts['host'] ?? '') !== 'docs.google.com') {
            return $url;
        }

        $path = $parts['path'] ?? '';
        parse_str($parts['query'] ?? '', $query);

        // Tab/sheet aktif bisa ditulis di query (?gid=123&single=true) atau di
        // fragment (#gid=123) seperti pada URL yang disalin dari address bar.
        // Tanpa gid, Google mengekspor tab pertama sehingga perubahan pada tab
        // yang sedang dipakai petugas tidak ikut tersinkron.
        if (! isset($query['gid']) && isset($parts['fragment'])) {
            parse_str($parts['fragment'], $fragment);

            if (isset($fragment['gid'])) {
                $query['gid'] = $fragment['gid'];
            }
        }

        if (str_ends_with($path, '/pubhtml')) {
            $path = substr($path, 0, -8).'/pub';
            $query['output'] = 'csv';
        } elseif (str_ends_with($path, '/pub')) {
            $query['output'] = 'csv';
        } elseif (str_ends_with($path, '/edit')) {
            $path = substr($path, 0, -5).'/export';
            $query['format'] = 'csv';
        }

        return ($parts['scheme'] ?? 'https').'://'.$parts['host'].$path
            .'?'.http_build_query([...$query, '_sync' => (string) now()->timestamp]);
    }

    private function readSpreadsheetRows(string $path): ?array
    {
        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($path);
        } catch (Throwable) {
            return null;
        }

        try {
            // formatData=true agar angka (mis. NIM/telepon) tetap tampil
            // utuh, bukan dalam notasi ilmiah.
            $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        } catch (Throwable) {
            $spreadsheet->disconnectWorksheets();

            return null;
        }

        $spreadsheet->disconnectWorksheets();

        return array_map(fn ($row) => $this->normalizeRow((array) $row), $rows);
    }

    /**
     * Normalisasi sel CSV: buang BOM, spasi berlebih, dan tanda kutip.
     */
    private function normalizeRow(array $row): array
    {
        return array_map(function ($value) {
            $value = (string) $value;
            $value = preg_replace('/^\xEF\xBB\xBF/', '', $value);

            return trim($value, " \t\n\r\0\x0B\"'");
        }, $row);
    }

    private function employeeTypeFromPosition(string $position): string
    {
        return BorrowerType::employeeTypeFromPosition($position);
    }

    /**
     * Petakan indeks kolom spreadsheet ke field database.
     * Mendukung header bahasa Indonesia maupun Inggris. Tanda baca dan
     * spasi diabaikan sehingga "NIM/NIP" atau "No. Telepon" dikenali.
     */
    private function mapColumns(array $header): ?array
    {
        $aliases = [
            'student_id' => ['studentid', 'nim', 'nimnip', 'nip', 'nipopsional', 'nidn', 'idmahasiswa', 'nomorinduk'],
            'name' => ['name', 'nama', 'namamahasiswa', 'namalengkap', 'namapeminjam'],
            'email' => ['email', 'surel'],
            'phone' => ['phone', 'telepon', 'notelepon', 'notelp', 'nomortelepon', 'nohp', 'hp', 'whatsapp'],
            // Role bebas menjadi peran peminjam; "Jabatan / Unit Kerja"
            // tetap disimpan terpisah sebagai tugas atau unit kerjanya.
            'role' => ['role', 'peran', 'peranan', 'jenisperan', 'jenis', 'jenispeminjam', 'jenisorang', 'tipe', 'kategori', 'statuskepegawaian', 'golongan'],
            'position' => [
                'jabatan',
                'jabatanfungsional',
                'jabatanunitkerja',
                'jabatanprogdi',
                'unitkerja',
                'tugas',
                'pekerjaan',
                'uraianpekerjaan',
                'programstudi',
                'prodi',
                'fakultas',
                'unit',
            ],
        ];

        $map = [];
        $normalized = array_map(fn ($value) => $this->normalizeColumn((string) $value), $header);

        foreach ($aliases as $field => $options) {
            foreach ($normalized as $index => $column) {
                if ($column !== '' && in_array($column, $options, true)) {
                    $map[$field] = $index;

                    break;
                }
            }
        }

        if (! isset($map['name'], $map['email'])) {
            return null;
        }

        return $map;
    }

    /**
     * Normalisasi nama kolom: huruf kecil, hanya huruf dan angka.
     */
    private function normalizeColumn(string $value): string
    {
        return (string) preg_replace('/[^a-z0-9]/', '', mb_strtolower(trim($value)));
    }
}
