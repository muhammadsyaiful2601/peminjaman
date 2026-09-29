<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role = 'assistant'): User
    {
        return User::factory()->create(['role' => $role]);
    }

    public function test_asisten_dapat_mengelola_data_mahasiswa_dan_mencarinya(): void
    {
        Sanctum::actingAs($this->staff());

        $create = $this->postJson('/api/students', [
            'student_id' => '2211082001',
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'phone' => '081234567890',
        ]);

        $create->assertCreated()
            ->assertJsonPath('student.student_id', '2211082001')
            ->assertJsonPath('student.name', 'Budi Santoso');

        $student = Student::firstOrFail();

        $this->getJson('/api/students?search=2211082001')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.email', 'budi@example.com');

        $this->putJson('/api/students/' . $student->id, [
            'student_id' => '2211082001',
            'name' => 'Budi Santoso Updated',
            'email' => 'budi.updated@example.com',
            'phone' => null,
        ])->assertOk()->assertJsonPath('student.name', 'Budi Santoso Updated');

        $this->deleteJson('/api/students/' . $student->id)
            ->assertOk()
            ->assertJsonPath('message', 'Data mahasiswa berhasil dihapus.');

        $this->assertDatabaseMissing('students', ['id' => $student->id]);
    }

    public function test_endpoint_mahasiswa_memerlukan_login(): void
    {
        $this->getJson('/api/students')->assertUnauthorized();
    }

    public function test_daftar_mahasiswa_dapat_dipaginasi_sepuluh_data_per_halaman(): void
    {
        Sanctum::actingAs($this->staff());

        // 25 mahasiswa; endpoint mengurutkan berdasarkan nama.
        foreach (range(1, 25) as $index) {
            Student::create([
                'student_id' => sprintf('22110820%02d', $index),
                'name' => sprintf('Mahasiswa %02d', $index),
                'email' => sprintf('mhs%02d@example.com', $index),
            ]);
        }

        $firstPage = $this->getJson('/api/students?page=1&per_page=10');
        $firstPage->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.total', 25)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.per_page', 10)
            // Urutan berdasarkan nama: "Mahasiswa 01" sampai "Mahasiswa 10".
            ->assertJsonPath('data.0.name', 'Mahasiswa 01')
            ->assertJsonPath('data.9.name', 'Mahasiswa 10');

        $this->getJson('/api/students?page=2&per_page=10')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('data.0.name', 'Mahasiswa 11')
            ->assertJsonPath('data.9.name', 'Mahasiswa 20');

        // Halaman terakhir hanya berisi sisa 5 data.
        $this->getJson('/api/students?page=3&per_page=10')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('data.0.name', 'Mahasiswa 21')
            ->assertJsonPath('data.4.name', 'Mahasiswa 25');
    }

    public function test_paginasi_mahasiswa_tetap_memperhatikan_pencarian(): void
    {
        Sanctum::actingAs($this->staff());

        foreach (range(1, 25) as $index) {
            Student::create([
                'student_id' => sprintf('22110820%02d', $index),
                'name' => sprintf('Mahasiswa %02d', $index),
                'email' => sprintf('mhs%02d@example.com', $index),
            ]);
        }

        // Pencarian pada kolom email tetap ikut dipaginasi: "mhs" cocok dengan
        // seluruh 25 data sehingga menjadi 3 halaman.
        $this->getJson('/api/students?search=mhs&page=1&per_page=10')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.total', 25)
            ->assertJsonPath('meta.last_page', 3);

        $this->getJson('/api/students?search=mhs&page=2&per_page=10')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('data.0.name', 'Mahasiswa 11')
            ->assertJsonPath('data.9.name', 'Mahasiswa 20');

        // Halaman terakhir hanya berisi sisa 5 data, bukan error.
        $this->getJson('/api/students?search=mhs&page=3&per_page=10')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('data.4.name', 'Mahasiswa 25');
    }

    public function test_daftar_mahasiswa_tanpa_per_page_tetap_mengembalikan_seluruh_data(): void
    {
        Sanctum::actingAs($this->staff());

        foreach (range(1, 15) as $index) {
            Student::create([
                'student_id' => sprintf('22110820%02d', $index),
                'name' => sprintf('Mahasiswa %02d', $index),
                'email' => sprintf('mhs%02d@example.com', $index),
            ]);
        }

        // Form peminjaman (NewLoan) masih meminta seluruh mahasiswa sekaligus,
        // jadi pemanggilan tanpa `per_page` tidak boleh ikut terpotong.
        $this->getJson('/api/students')
            ->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.total', 15)
            ->assertJsonMissingPath('meta.last_page');
    }

    public function test_per_page_mahasiswa_ditolak_bila_di_luar_batas(): void
    {
        Sanctum::actingAs($this->staff());

        $this->getJson('/api/students?per_page=5000')
            ->assertStatus(422)
            ->assertJsonValidationErrors('per_page');
    }

    public function test_peminjam_hanya_dapat_mengelola_data_mahasiswa_dengan_role_petugas(): void
    {
        Sanctum::actingAs($this->staff('borrower'));

        $this->postJson('/api/students', [
            'student_id' => '2211082001',
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
        ])->assertForbidden();
    }

    public function test_petugas_dapat_mengimpor_mahasiswa_dari_csv(): void
    {
        Sanctum::actingAs($this->staff());

        $csv = implode("\n", [
            'student_id,name,email,phone',
            '2211082001,Budi Santoso,budi@example.com,081234567890',
            '2211082002,Siti Aminah,siti@example.com,',
        ]);

        $response = $this->post('/api/students/import', [
            'file' => $this->fakeCsvUpload($csv, 'mahasiswa.csv'),
        ]);

        $response->assertOk()
            ->assertJsonPath('imported', 2)
            ->assertJsonPath('updated', 0);

        $this->assertDatabaseHas('students', [
            'student_id' => '2211082001',
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
        ]);
        $this->assertDatabaseHas('students', [
            'student_id' => '2211082002',
            'email' => 'siti@example.com',
        ]);
    }

    public function test_petugas_dapat_mengimpor_mahasiswa_dari_csv_google_sheets_di_web(): void
    {
        Http::fake([
            'https://docs.google.com/*' => Http::response(implode("\n", [
                'NIM,Nama,Email,No. Telepon',
                '2211082001,Budi Santoso,budi@example.com,081234567890',
            ])),
        ]);
        Sanctum::actingAs($this->staff());

        $response = $this->postJson('/api/students/import/csv-url', [
            'url' => 'https://docs.google.com/spreadsheets/d/example/pubhtml',
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('imported', 1);
        $this->assertDatabaseHas('students', ['student_id' => '2211082001']);
        $this->assertDatabaseHas('app_settings', [
            'key' => 'student_sync_csv_url',
            'value' => 'https://docs.google.com/spreadsheets/d/example/pubhtml',
        ]);
    }

    public function test_aplikasi_desktop_dapat_mengimpor_csv_google_sheets_lewat_kunci_desktop(): void
    {
        config(['app.desktop_key' => 'test-desktop-key']);
        Http::fake([
            'https://docs.google.com/*' => Http::response(implode("\n", [
                'NIM,Nama,Email,No. Telepon',
                '2211082002,Siti Aminah,siti@example.com,081298765432',
            ])),
        ]);

        $this->postJson('/api/desktop/students/import-csv-url', [
            'url' => 'https://docs.google.com/spreadsheets/d/example/pubhtml',
        ], ['X-Desktop-Key' => 'test-desktop-key'])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('imported', 1);

        $this->assertDatabaseHas('students', ['student_id' => '2211082002']);
    }

    public function test_aplikasi_desktop_mendapat_pesan_validasi_saat_csv_google_sheets_tidak_dapat_dihubungi(): void
    {
        config(['app.desktop_key' => 'test-desktop-key']);
        Http::fake(function () {
            throw new \RuntimeException('Connection refused');
        });

        $this->postJson('/api/desktop/students/import-csv-url', [
            'url' => 'https://docs.google.com/spreadsheets/d/example/pubhtml',
        ], ['X-Desktop-Key' => 'test-desktop-key'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'CSV Google Sheets tidak dapat dihubungi.');
    }

    public function test_impor_mahasiswa_dari_aplikasi_desktop_butuh_kunci_desktop(): void
    {
        config(['app.desktop_key' => 'test-desktop-key']);

        $payload = ['url' => 'https://docs.google.com/spreadsheets/d/example/pubhtml'];

        $this->postJson('/api/desktop/students/import-csv-url', $payload)->assertStatus(404);
        $this->postJson('/api/desktop/students/import-csv-url', $payload, ['X-Desktop-Key' => 'salah'])
            ->assertStatus(404);

        $this->assertDatabaseCount('students', 0);
    }

    public function test_impor_menerima_header_bahasa_indonesia_dan_memperbarui_nim_yang_sudah_ada(): void
    {
        Sanctum::actingAs($this->staff());

        Student::create([
            'student_id' => '2211082001',
            'name' => 'Budi Lama',
            'email' => 'budi@example.com',
        ]);

        $csv = implode("\n", [
            'NIM,Nama,Email,No. Telepon',
            '2211082001,Budi Santoso Baru,budi@example.com,081234567890',
            '2211082003,Andi Wijaya,andi@example.com,',
            '2211082004,Tidak Valid,bukan-email,',
        ]);

        $response = $this->post('/api/students/import', [
            'file' => $this->fakeCsvUpload($csv, 'mahasiswa.csv'),
        ]);

        $response->assertOk()
            ->assertJsonPath('imported', 1)
            ->assertJsonPath('updated', 1);

        $this->assertDatabaseHas('students', [
            'student_id' => '2211082001',
            'name' => 'Budi Santoso Baru',
        ]);
        $this->assertDatabaseHas('students', ['student_id' => '2211082003']);

        // Baris dengan email tidak valid dilewati, tidak menggagalkan impor.
        $this->assertDatabaseMissing('students', ['student_id' => '2211082004']);
        $this->assertCount(1, $response->json('errors'));
    }

    public function test_impor_menolak_file_tanpa_header_yang_dikenali(): void
    {
        Sanctum::actingAs($this->staff());

        $csv = "kolom_salah,nilai\n1,2\n";

        $this->postJson('/api/students/import', [
            'file' => $this->fakeCsvUpload($csv, 'salah.csv'),
        ])->assertStatus(422);

        $this->assertDatabaseCount('students', 0);
    }

    public function test_impor_hanya_untuk_petugas(): void
    {
        Sanctum::actingAs($this->staff('borrower'));

        $this->postJson('/api/students/import', [
            'file' => $this->fakeCsvUpload("student_id,name,email\n1,A,a@e.com\n", 'm.csv'),
        ])->assertForbidden();
    }

    public function test_petugas_dapat_mengunduh_template_impor(): void
    {
        Sanctum::actingAs($this->staff());

        $response = $this->getJson('/api/students/import/template');

        $response->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="template-impor-mahasiswa.xls"');

        $content = $response->getContent();
        $this->assertStringContainsString('TEMPLATE IMPOR DATA MAHASISWA', $content);
        $this->assertStringContainsString('NIM/NIP', $content);
        $this->assertStringContainsString('Nama', $content);
        $this->assertStringContainsString('Email', $content);
        $this->assertStringContainsString('No. Telepon', $content);

        // Template tidak memuat data contoh.
        $this->assertStringNotContainsString('Budi', $content);
        $this->assertStringNotContainsString('2211082001', $content);
    }

    public function test_impor_menerima_file_template_dengan_judul_dan_petunjuk(): void
    {
        Sanctum::actingAs($this->staff());

        // Simulasi template yang diekspor ke CSV: baris judul, petunjuk,
        // lalu header tanpa data contoh — pengguna tinggal mengisi.
        $csv = implode("\n", [
            'TEMPLATE IMPOR DATA MAHASISWA,,,',
            'Isi data mulai baris 4 ke bawah. Baris judul, petunjuk, dan header tidak perlu diubah.,,,',
            'NIM/NIP,Nama,Email,No. Telepon',
            '2211082001,Budi Santoso,budi@example.com,081234567890',
            '2211082002,Siti Aminah,siti@example.com,',
        ]);

        $response = $this->post('/api/students/import', [
            'file' => $this->fakeCsvUpload($csv, 'template.csv'),
        ]);

        $response->assertOk()
            ->assertJsonPath('imported', 2)
            ->assertJsonPath('updated', 0);

        $this->assertDatabaseHas('students', [
            'student_id' => '2211082001',
            'name' => 'Budi Santoso',
        ]);
    }

    public function test_petugas_dapat_mengimpor_file_xlsx(): void
    {
        Sanctum::actingAs($this->staff());

        $path = tempnam(sys_get_temp_dir(), 'xlsx_').'.xlsx';
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray([
            ['NIM/NIP', 'Nama', 'Email', 'No. Telepon'],
            ['2211082001', 'Budi Santoso', 'budi@example.com', '081234567890'],
            ['2211082002', 'Siti Aminah', 'siti@example.com', null],
        ], null, 'A1');
        \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);
        $spreadsheet->disconnectWorksheets();

        $response = $this->post('/api/students/import', [
            'file' => new \Illuminate\Http\UploadedFile($path, 'mahasiswa.xlsx', null, null, true),
        ]);

        $response->assertOk()
            ->assertJsonPath('imported', 2)
            ->assertJsonPath('updated', 0);

        $this->assertDatabaseHas('students', [
            'student_id' => '2211082001',
            'name' => 'Budi Santoso',
            'phone' => '081234567890',
        ]);
        $this->assertDatabaseHas('students', ['student_id' => '2211082002']);

        @unlink($path);
    }

    public function test_impor_menolak_format_file_tak_kenali(): void
    {
        Sanctum::actingAs($this->staff());

        $this->postJson('/api/students/import', [
            'file' => \Illuminate\Http\UploadedFile::fake()->createWithContent('data.pdf', '%PDF-1.4 dummy'),
        ])->assertStatus(422);

        $this->assertDatabaseCount('students', 0);
    }

    public function test_sinkronisasi_otomatis_mengikuti_perubahan_spreadsheet(): void
    {
        Sanctum::actingAs($this->staff());

        // Respons pertama = isi spreadsheet awal, respons kedua = spreadsheet
        // setelah diubah petugas.
        Http::fake([
            'https://docs.google.com/*' => Http::sequence()
                ->push(implode("\n", [
                    'NIM,Nama,Email,No. Telepon',
                    '2211082001,Budi Santoso,budi@example.com,081234567890',
                ]))
                ->push(implode("\n", [
                    'NIM,Nama,Email,No. Telepon',
                    '2211082001,Budi Santoso Baru,budi@example.com,081200000000',
                ])),
        ]);

        $url = 'https://docs.google.com/spreadsheets/d/example/pub?output=csv';

        $this->postJson('/api/students/import/csv-url', ['url' => $url])
            ->assertOk()
            ->assertJsonPath('imported', 1);

        // Petugas mengubah nama & nomor telepon pada spreadsheet; siklus
        // sinkronisasi otomatis berikutnya wajib mengikuti perubahan itu.
        $this->postJson('/api/students/import/csv-url', ['url' => $url])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('updated', 1)
            ->assertJsonPath('unchanged', 0);

        $this->assertDatabaseCount('students', 1);
        $this->assertDatabaseHas('students', [
            'student_id' => '2211082001',
            'name' => 'Budi Santoso Baru',
            'phone' => '081200000000',
        ]);

        // Waktu sinkronisasi terakhir tersimpan agar halaman Data Mahasiswa
        // dapat menampilkannya setelah dimuat ulang.
        $this->assertNotNull(AppSetting::getValue('student_sync_last_at'));
        $this->assertNotNull($this->getJson('/api/students/import/source')->json('last_synced_at'));
    }

    public function test_sinkronisasi_ulang_tanpa_perubahan_tidak_dihitung_sebagai_perubahan(): void
    {
        Sanctum::actingAs($this->staff());

        Student::create([
            'student_id' => '2211082001',
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'phone' => '081234567890',
        ]);
        Student::create([
            'student_id' => '2211082002',
            'name' => 'Siti Aminah',
            'email' => 'siti@example.com',
        ]);

        $csv = implode("\n", [
            'NIM,Nama,Email,No. Telepon',
            '2211082001,Budi Santoso,budi@example.com,081234567890',
            '2211082002,Siti Aminah,siti@example.com,',
        ]);

        $this->post('/api/students/import', [
            'file' => $this->fakeCsvUpload($csv, 'mahasiswa.csv'),
        ])->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('imported', 0)
            ->assertJsonPath('updated', 0)
            ->assertJsonPath('unchanged', 2);

        $this->assertDatabaseCount('students', 2);
    }

    public function test_sinkronisasi_mengikuti_perbaikan_nim_di_spreadsheet(): void
    {
        Sanctum::actingAs($this->staff());

        Student::create([
            'student_id' => '2211082001',
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'phone' => '081234567890',
        ]);

        // Petugas memperbaiki NIM pada spreadsheet (email tetap sama).
        $csv = implode("\n", [
            'NIM,Nama,Email,No. Telepon',
            '2211082999,Budi Santoso,budi@example.com,081234567890',
        ]);

        $this->post('/api/students/import', [
            'file' => $this->fakeCsvUpload($csv, 'mahasiswa.csv'),
        ])->assertOk()
            ->assertJsonPath('imported', 0)
            ->assertJsonPath('updated', 1)
            ->assertJsonPath('errors', []);

        // NIM ikut berubah tanpa membuat data ganda.
        $this->assertDatabaseCount('students', 1);
        $this->assertDatabaseHas('students', [
            'student_id' => '2211082999',
            'email' => 'budi@example.com',
        ]);
        $this->assertDatabaseMissing('students', ['student_id' => '2211082001']);
    }

    public function test_sinkronisasi_menolak_email_yang_dipakai_mahasiswa_lain(): void
    {
        Sanctum::actingAs($this->staff());

        Student::create(['student_id' => '2211082001', 'name' => 'Budi Santoso', 'email' => 'budi@example.com']);
        Student::create(['student_id' => '2211082002', 'name' => 'Siti Aminah', 'email' => 'siti@example.com']);

        $csv = implode("\n", [
            'NIM,Nama,Email,No. Telepon',
            '2211082001,Budi Santoso,siti@example.com,',
        ]);

        $response = $this->post('/api/students/import', [
            'file' => $this->fakeCsvUpload($csv, 'mahasiswa.csv'),
        ]);

        $response->assertStatus(422)->assertJsonCount(1, 'errors');

        // Data kedua mahasiswa tidak tertukar.
        $this->assertDatabaseHas('students', ['student_id' => '2211082001', 'email' => 'budi@example.com']);
        $this->assertDatabaseHas('students', ['student_id' => '2211082002', 'email' => 'siti@example.com']);
    }

    public function test_sinkronisasi_memakai_tab_spreadsheet_yang_dipilih_dan_tanpa_cache(): void
    {
        Sanctum::actingAs($this->staff());

        Http::fake([
            'https://docs.google.com/*' => Http::response(implode("\n", [
                'NIM,Nama,Email',
                '2211082001,Budi Santoso,budi@example.com',
            ])),
        ]);

        // URL yang disalin dari address bar menyimpan tab pada fragment
        // (#gid=...). Tanpa gid, Google mengekspor tab pertama sehingga
        // perubahan pada tab yang dipakai petugas tidak ikut tersinkron.
        $this->postJson('/api/students/import/csv-url', [
            'url' => 'https://docs.google.com/spreadsheets/d/example/edit?usp=sharing#gid=12345',
        ])->assertOk();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/export')
                && str_contains($request->url(), 'format=csv')
                && str_contains($request->url(), 'gid=12345')
                // Cache-buster agar Google tidak mengirim CSV lama.
                && str_contains($request->url(), '_sync=');
        });
    }

    private function fakeCsvUpload(string $content, string $filename): \Illuminate\Http\UploadedFile
    {
        return \Illuminate\Http\UploadedFile::fake()->createWithContent($filename, $content);
    }
}
