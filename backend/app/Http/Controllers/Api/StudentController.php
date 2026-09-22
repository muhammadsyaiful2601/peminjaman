<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;
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

            // Cek bentrok dengan data yang sudah ada di database.
            $conflict = Student::where(function ($query) use ($data) {
                $query->where('student_id', $data['student_id'])
                    ->orWhere('email', $data['email']);
            })->first();

            if ($conflict && $conflict->student_id !== $data['student_id']) {
                $errors[] = "Baris {$rowNumber}: Email {$data['email']} sudah dipakai NIM {$conflict->student_id}.";

                continue;
            }

            $seenStudentIds[$data['student_id']] = true;
            $seenEmails[$data['email']] = true;

            $existing = $conflict;
            $payload = [
                'student_id' => $data['student_id'],
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] !== '' ? $data['phone'] : null,
            ];

            if ($existing) {
                $existing->update($payload);
                $updated++;
            } else {
                Student::create($payload);
                $imported++;
            }
        }

        if ($imported === 0 && $updated === 0) {
            return response()->json([
                'message' => 'Tidak ada data yang diimpor. Periksa kembali isi file.',
                'imported' => 0,
                'updated' => 0,
                'errors' => $errors,
            ], 422);
        }

        return response()->json([
            'message' => "Impor selesai: {$imported} mahasiswa baru ditambahkan, {$updated} diperbarui."
                . (count($errors) > 0 ? ' ' . count($errors) . ' baris dilewati.' : ''),
            'imported' => $imported,
            'updated' => $updated,
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
