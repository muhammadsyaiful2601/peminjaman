<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    private function fakeCsvUpload(string $content, string $filename): \Illuminate\Http\UploadedFile
    {
        return \Illuminate\Http\UploadedFile::fake()->createWithContent($filename, $content);
    }
}
