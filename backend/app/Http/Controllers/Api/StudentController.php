<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));

        $students = Student::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('student_id', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $students,
            'meta' => [
                'total' => $students->count(),
                'search' => $search,
            ],
        ]);
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

        return response()->json([
            'message' => 'Data mahasiswa berhasil diperbarui.',
            'student' => $student->refresh(),
        ]);
    }

    public function destroy(Student $student)
    {
        $student->delete();

        return response()->json(['message' => 'Data mahasiswa berhasil dihapus.']);
    }

    public function importSource()
    {
        return response()->json([
            'url' => AppSetting::getValue('student_sync_csv_url', ''),
            // Waktu sinkronisasi otomatis terakhir yang berhasil, agar halaman
            // Data Mahasiswa dapat menampilkan "sinkron terakhir" walaupun
            // halaman baru dibuka/dimuat ulang.
            'last_synced_at' => AppSetting::getValue('student_sync_last_at'),
        ]);
    }

    public function saveImportSource(Request $request)
    {
        $validated = $request->validate([
            'url' => ['required', 'url', 'max:2000'],
        ]);

        AppSetting::setValue('student_sync_csv_url', $validated['url']);

        return response()->json([
            'message' => 'URL spreadsheet berhasil disimpan.',
            'url' => $validated['url'],
        ]);
    }

    /**
     * Impor data mahasiswa dari file spreadsheet (CSV hasil unduhan
     * Google Sheets / Excel). Baris dengan NIM yang sudah ada akan
     * diperbarui, baris baru akan ditambahkan.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:5120', 'mimes:csv,txt,xlsx,xls'],
        ]);

        $rows = $this->readRows($request->file('file'));

        if ($rows === null) {
            return response()->json([
                'message' => 'File tidak dapat dibaca. Gunakan format CSV, XLSX, atau XLS.',
            ], 422);
        }

        return $this->importRows($rows);
    }

    /**
     * Ambil CSV Google Sheets yang sudah dipublikasikan untuk penggunaan web.
     * Tidak menggunakan Google Sheets API atau API key.
     */
    public function importFromPublishedCsv(Request $request)
    {
        $validated = $request->validate([
            'url' => ['required', 'url', 'max:2000'],
        ]);

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

        $result = $this->importRows($rows);

        if ($result->getStatusCode() < 300) {
            AppSetting::setValue('student_sync_csv_url', $validated['url']);
            AppSetting::setValue('student_sync_last_at', now()->toIso8601String());
        }

        return $result;
    }

    private function importRows(array $rows)
    {

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
                'message' => 'Header spreadsheet tidak dikenali. Gunakan template: NIM/NIP, Nama, Email, No. Telepon.',
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
                'student_id' => trim((string) ($row[$columnMap['student_id']] ?? '')),
                'name' => trim((string) ($row[$columnMap['name']] ?? '')),
                'email' => strtolower(trim((string) ($row[$columnMap['email']] ?? ''))),
                'phone' => isset($columnMap['phone']) ? trim((string) ($row[$columnMap['phone']] ?? '')) : '',
            ];

            if ($data['student_id'] === '' && $data['name'] === '' && $data['email'] === '') {
                continue;
            }

            if (isset($seenStudentIds[$data['student_id']])) {
                $errors[] = "Baris {$rowNumber}: NIM {$data['student_id']} duplikat di dalam file.";

                continue;
            }

            if (isset($seenEmails[$data['email']])) {
                $errors[] = "Baris {$rowNumber}: Email {$data['email']} duplikat di dalam file.";

                continue;
            }

            $validator = Validator::make($data, [
                'student_id' => ['required', 'string', 'max:50'],
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255'],
                'phone' => ['nullable', 'string', 'max:20'],
            ]);

            if ($validator->fails()) {
                $errors[] = "Baris {$rowNumber}: " . implode(' ', $validator->errors()->all());

                continue;
            }

            // Cocokkan baris spreadsheet dengan data yang sudah tersimpan:
            // NIM dipakai sebagai kunci utama, email sebagai kunci cadangan.
            // Dengan begitu perbaikan NIM pada spreadsheet ikut terpakai
            // (bukan ditolak sebagai bentrok) sebagaimana perubahan data lain.
            $byStudentId = Student::where('student_id', $data['student_id'])->first();
            $byEmail = Student::where('email', $data['email'])->first();

            // Email/NIM milik dua mahasiswa berbeda = bentrok sungguhan.
            if ($byStudentId && $byEmail && $byStudentId->id !== $byEmail->id) {
                $errors[] = "Baris {$rowNumber}: Email {$data['email']} sudah dipakai NIM {$byEmail->student_id}.";

                continue;
            }

            $seenStudentIds[$data['student_id']] = true;
            $seenEmails[$data['email']] = true;

            $existing = $byStudentId ?? $byEmail;
            $payload = [
                'student_id' => $data['student_id'],
                'name' => $data['name'],
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

        $summary = ["{$imported} mahasiswa baru ditambahkan", "{$updated} diperbarui"];

        if ($unchanged > 0) {
            $summary[] = "{$unchanged} tanpa perubahan";
        }

        return response()->json([
            'ok' => true,
            'message' => 'Impor selesai: '.implode(', ', $summary).'.'
                . (count($errors) > 0 ? ' ' . count($errors) . ' baris dilewati.' : ''),
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
            $studentIdRule .= ',' . $student->id;
            $emailRule .= ',' . $student->id;
        }

        return $request->validate([
            'student_id' => ['required', 'string', 'max:50', $studentIdRule],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', $emailRule],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);
    }

    /**
     * Unduh template impor: file .xls (HTML table) yang berisi judul,
     * petunjuk, dan header berformat tanpa data contoh. File ini bisa
     * langsung dibuka di Excel atau diunggah ke Google Sheets.
     */
    public function downloadTemplate()
    {
        $html = <<<'HTML'
            <html xmlns:x="urn:schemas-microsoft-com:office:excel">
            <head>
                <meta charset="UTF-8">
                <!--[if gte mso 9]><xml>
                    <x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>
                        <x:Name>Data Mahasiswa</x:Name>
                        <x:WorksheetOptions><x:Panes></x:Panes></x:WorksheetOptions>
                    </x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook>
                </xml><![endif]-->
            </head>
            <body>
                <table border="0">
                    <tr><td colspan="4" style="font-size:14pt; font-weight:bold;">TEMPLATE IMPOR DATA MAHASISWA</td></tr>
                    <tr><td colspan="4" style="font-size:10pt; color:#555555;">Isi data mulai baris 4 ke bawah. Baris judul, petunjuk, dan header tidak perlu diubah.</td></tr>
                    <tr>
                        <td style="background-color:#0e7490; color:#ffffff; font-weight:bold; border:1px solid #155e75; padding:6px 10px;">NIM/NIP</td>
                        <td style="background-color:#0e7490; color:#ffffff; font-weight:bold; border:1px solid #155e75; padding:6px 10px;">Nama</td>
                        <td style="background-color:#0e7490; color:#ffffff; font-weight:bold; border:1px solid #155e75; padding:6px 10px;">Email</td>
                        <td style="background-color:#0e7490; color:#ffffff; font-weight:bold; border:1px solid #155e75; padding:6px 10px;">No. Telepon</td>
                    </tr>
                </table>
            </body>
            </html>
            HTML;

        return response($html)
            ->header('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="template-impor-mahasiswa.xls"');
    }

    /**
     * Baca seluruh baris file impor. CSV/TXT dibaca dengan fgetcsv,
     * sedangkan XLSX/XLS dibaca dengan PhpSpreadsheet.
     * Mengembalikan null bila file tidak dapat dibaca.
     */
    private function readRows(\Illuminate\Http\UploadedFile $file): ?array
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
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
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

    /**
     * Petakan indeks kolom spreadsheet ke field database.
     * Mendukung header bahasa Indonesia maupun Inggris. Tanda baca dan
     * spasi diabaikan sehingga "NIM/NIP" atau "No. Telepon" dikenali.
     */
    private function mapColumns(array $header): ?array
    {
        $aliases = [
            'student_id' => ['studentid', 'nim', 'nimnip', 'nip', 'nidn', 'idmahasiswa', 'nomorinduk'],
            'name' => ['name', 'nama', 'namamahasiswa', 'namalengkap'],
            'email' => ['email', 'surel'],
            'phone' => ['phone', 'telepon', 'notelepon', 'notelp', 'nomortelepon', 'nohp', 'hp', 'whatsapp'],
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

        if (! isset($map['student_id'], $map['name'], $map['email'])) {
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
