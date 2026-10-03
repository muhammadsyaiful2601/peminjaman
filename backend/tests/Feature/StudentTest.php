<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Item;
use App\Models\Loan;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
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

        $this->putJson('/api/students/'.$student->id, [
            'student_id' => '2211082001',
            'name' => 'Budi Santoso Updated',
            'email' => 'budi.updated@example.com',
            'phone' => null,
        ])->assertOk()->assertJsonPath('student.name', 'Budi Santoso Updated');

        $this->deleteJson('/api/students/'.$student->id)
            ->assertOk()
            ->assertJsonPath('message', 'Data peminjam berhasil dihapus.');

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

    public function test_petugas_dapat_menambah_pegawai_tendik_dosen_dan_umum(): void
    {
        Sanctum::actingAs($this->staff());

        $cases = [
            ['nip' => '198001012005011001', 'type' => 'tendik', 'role' => 'Tendik', 'position' => 'Staf Bagian Keuangan'],
            ['nip' => '197505052000031002', 'type' => 'dosen', 'role' => 'Dosen', 'position' => 'Dosen Teknik Informatika'],
            ['nip' => '081234567890', 'type' => 'umum', 'role' => null, 'position' => null],
            ['nip' => null, 'type' => 'tendik', 'role' => 'Rumah Tangga', 'position' => 'Melayani kebutuhan rumah tangga'],
        ];

        foreach ($cases as $index => $case) {
            $this->postJson('/api/students', [
                'student_id' => $case['nip'],
                'name' => 'Pegawai '.($index + 1),
                'type' => $case['type'],
                'role' => $case['role'],
                'position' => $case['position'],
                'email' => 'pegawai'.($index + 1).'@pnp.ac.id',
            ])->assertCreated()
                ->assertJsonPath('student.type', $case['type'])
                ->assertJsonPath('student.position', $case['position']);
        }

        $this->assertDatabaseHas('students', ['student_id' => '197505052000031002', 'type' => 'dosen']);
        $this->assertDatabaseHas('students', ['student_id' => '081234567890', 'type' => 'umum']);
        $this->assertDatabaseHas('students', [
            'student_id' => null,
            'type' => 'tendik',
            'role' => 'Rumah Tangga',
            'email' => 'pegawai4@pnp.ac.id',
        ]);
    }

    public function test_jabatan_unit_kerja_menentukan_kategori_pegawai_tanpa_mengubah_mahasiswa(): void
    {
        Sanctum::actingAs($this->staff());

        $dosenResponse = $this->postJson('/api/students', [
            'student_id' => null,
            'name' => 'Dosen Pegawai',
            'type' => 'tendik',
            'role' => 'Tendik',
            'position' => 'Dosen/D3 Sistem Informasi',
            'email' => 'dosen-pegawai@example.com',
        ])->assertCreated()
            ->assertJsonPath('student.type', 'dosen');

        $this->putJson('/api/students/'.$dosenResponse->json('student.id'), [
            'student_id' => null,
            'name' => 'Dosen Pegawai',
            'type' => 'tendik',
            'role' => 'Rumah Tangga',
            'position' => 'Teknisi / D3 Sistem Informasi',
            'email' => 'dosen-pegawai@example.com',
        ])->assertOk()
            ->assertJsonPath('student.type', 'tendik');

        $this->postJson('/api/students', [
            'student_id' => null,
            'name' => 'Pegawai Rumah Tangga',
            'type' => 'dosen',
            'role' => 'Dosen',
            'position' => 'Rumah Tangga',
            'email' => 'rumah-tangga@example.com',
        ])->assertCreated()
            ->assertJsonPath('student.type', 'tendik');

        $this->postJson('/api/students', [
            'student_id' => '2211082001',
            'name' => 'Mahasiswa Dosen',
            'type' => 'mahasiswa',
            'role' => 'Dosen wali',
            'position' => 'Dosen/D3 Sistem Informasi',
            'email' => 'mahasiswa@example.com',
        ])->assertCreated()
            ->assertJsonPath('student.type', 'mahasiswa');
    }

    public function test_jenis_peminjam_tidak_dikenal_ditolak(): void
    {
        Sanctum::actingAs($this->staff());

        $this->postJson('/api/students', [
            'student_id' => '12345',
            'name' => 'Orang random',
            'type' => 'alien',
            'email' => 'alien@example.com',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    public function test_peminjam_lama_tanpa_jenis_tetap_dibaca_sebagai_mahasiswa(): void
    {
        Sanctum::actingAs($this->staff());

        // Pemanggil lama tidak mengirim `type` sama sekali — kolomnya defaulted
        // "mahasiswa" oleh database, jadi data lama tidak berubah jenis.
        Student::create([
            'student_id' => '2211082001',
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
        ]);

        $this->getJson('/api/students?per_page=10')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'mahasiswa')
            ->assertJsonPath('meta.by_type.mahasiswa', 1);

        // Disimpan ulang tanpa mengirim `type` juga tidak mengubah jenis.
        $student = Student::firstOrFail();
        $this->putJson('/api/students/'.$student->id, [
            'student_id' => '2211082001',
            'name' => 'Budi Santoso Updated',
            'email' => 'budi@example.com',
        ])->assertOk()->assertJsonPath('student.type', 'mahasiswa');
    }

    public function test_daftar_peminjam_dapat_difilter_per_jenis_beserta_jumlahnya(): void
    {
        Sanctum::actingAs($this->staff());

        $seed = [
            ['mahasiswa', '2211082001', 'Mhs Satu'],
            ['mahasiswa', '2211082002', 'Mhs Dua'],
            ['tendik', '198001012005011001', 'Tendik Satu'],
            ['dosen', '197505052000031002', 'Dosen Satu'],
            ['umum', '081234567890', 'Umum Satu'],
        ];
        foreach ($seed as [$type, $id, $name]) {
            Student::create([
                'student_id' => $id,
                'name' => $name,
                'type' => $type,
                'email' => strtolower(str_replace(' ', '', $name)).'@pnp.ac.id',
            ]);
        }

        $this->getJson('/api/students?type=dosen&per_page=10')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Dosen Satu')
            ->assertJsonPath('meta.type', 'dosen')
            ->assertJsonPath('meta.total', 1)
            // Jumlah jenis lain tetap dihitung agar badge pada tab tidak kosong.
            ->assertJsonPath('meta.by_type.all', 5)
            ->assertJsonPath('meta.by_type.mahasiswa', 2)
            ->assertJsonPath('meta.by_type.tendik', 1)
            ->assertJsonPath('meta.by_type.dosen', 1)
            ->assertJsonPath('meta.by_type.umum', 1);

        $this->getJson('/api/students?type=mahasiswa&per_page=10')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);

        // Peminjam umum boleh tanpa jabatan.
        $this->getJson('/api/students?type=umum&per_page=10')
            ->assertOk()
            ->assertJsonPath('data.0.position', null);

        $this->getJson('/api/students?type=aliens&per_page=10')
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    public function test_transaksi_menyimpan_jenis_peminjam_dan_membaca_lama_sebagai_mahasiswa(): void
    {
        Sanctum::actingAs($this->staff());
        $item = Item::create([
            'name' => 'Kabel HDMI',
            'item_code' => 'BRG-001',
            'category' => 'Peralatan',
            'stock' => 10,
        ]);

        $loan = Loan::create([
            'uuid' => (string) Str::uuid(),
            'loan_code' => 'PJM-2026-9001',
            'item_id' => $item->id,
            'qty' => 1,
            'borrower_name' => 'Siti Aminah',
            'borrower_email' => 'siti@pnp.ac.id',
            'borrower_type' => 'tendik',
            'status' => 'borrowed',
            'created_by' => $this->staff()->id,
        ]);
        $this->assertSame('tendik', $loan->fresh()->borrower_type);

        // Transaksi lama tidak punya jenis -> dibaca sebagai mahasiswa.
        $legacy = Loan::create([
            'uuid' => (string) Str::uuid(),
            'loan_code' => 'PJM-2026-9002',
            'item_id' => $item->id,
            'qty' => 1,
            'borrower_name' => 'Budi Santoso',
            'borrower_email' => 'budi@example.com',
            'status' => 'borrowed',
            'created_by' => $this->staff()->id,
        ]);
        $this->assertNull($legacy->fresh()->getRawOriginal('borrower_type'));
        $this->assertSame('mahasiswa', $legacy->fresh()->borrower_type);
    }

    public function test_impor_menyimpan_role_dan_unit_kerja_dengan_nip_opsional(): void
    {
        Sanctum::actingAs($this->staff());

        $csv = "Role,Nama,Jabatan / Unit Kerja,Email,No. Telepon\n"
            ."Rumah Tangga,Siti Aminah,Menangani kebersihan gedung,siti@pnp.ac.id,0813\n"
            ."Rumah Tangga,Andi Saputra,Dosen/D3 Sistem Informasi,andi@pnp.ac.id,0814\n";

        $this->post('/api/students/import', [
            'file' => UploadedFile::fake()->createWithContent('peminjam.csv', $csv),
            'type' => 'tendik',
        ], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('imported', 2);

        $this->assertDatabaseHas('students', [
            'student_id' => null,
            'type' => 'tendik',
            'role' => 'Rumah Tangga',
            'position' => 'Menangani kebersihan gedung',
            'email' => 'siti@pnp.ac.id',
        ]);
        $this->assertDatabaseHas('students', [
            'student_id' => null,
            'type' => 'dosen',
            'role' => 'Rumah Tangga',
            'position' => 'Dosen/D3 Sistem Informasi',
            'email' => 'andi@pnp.ac.id',
        ]);
    }

    public function test_impor_pegawai_mengategorikan_dari_jabatan_bukan_role(): void
    {
        Sanctum::actingAs($this->staff());

        $csv = "Role,Nama,Jabatan / Unit Kerja,Email\n"
            ."Tendik,Andi Saputra,Wakil Direktur / Dosen D3 Sistem Informasi,andi@example.com\n"
            ."Dosen,Siti Aminah,Rumah Tangga,siti@example.com\n";

        $this->post('/api/students/import', [
            'file' => UploadedFile::fake()->createWithContent('pegawai.csv', $csv),
            'type' => 'tendik',
        ], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('imported', 2);

        $this->assertDatabaseHas('students', ['email' => 'andi@example.com', 'type' => 'dosen']);
        $this->assertDatabaseHas('students', ['email' => 'siti@example.com', 'type' => 'tendik']);
    }

    public function test_migrasi_mengategorikan_ulang_pegawai_dari_jabatan_tanpa_mengubah_mahasiswa(): void
    {
        Student::create([
            'student_id' => null,
            'name' => 'Dosen Lama',
            'type' => 'tendik',
            'role' => 'Tendik',
            'position' => 'Dosen/D3 Sistem Informasi',
            'email' => 'dosen-lama@example.com',
        ]);
        Student::create([
            'student_id' => null,
            'name' => 'Tendik Lama',
            'type' => 'dosen',
            'role' => 'Dosen',
            'position' => 'Rumah Tangga',
            'email' => 'tendik-lama@example.com',
        ]);
        Student::create([
            'student_id' => '2211082001',
            'name' => 'Mahasiswa',
            'type' => 'mahasiswa',
            'position' => 'Dosen wali',
            'email' => 'mahasiswa-posisi@example.com',
        ]);

        $migration = require database_path('migrations/2026_10_03_000000_reclassify_employees_from_position.php');
        $migration->up();

        $this->assertDatabaseHas('students', ['email' => 'dosen-lama@example.com', 'type' => 'dosen']);
        $this->assertDatabaseHas('students', ['email' => 'tendik-lama@example.com', 'type' => 'tendik']);
        $this->assertDatabaseHas('students', ['email' => 'mahasiswa-posisi@example.com', 'type' => 'mahasiswa']);
    }

    public function test_impor_menolak_jenis_yang_tidak_dikenal(): void
    {
        Sanctum::actingAs($this->staff());

        $csv = "NIM/NIP,Nama,Jenis,Email\n"
            ."2211082001,Budi Santoso,,budi@example.com\n"
            ."12345,Orang Asing,alien,alien@example.com\n";

        $this->post('/api/students/import', [
            'file' => UploadedFile::fake()->createWithContent('peminjam.csv', $csv),
        ], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('imported', 1)
            ->assertJsonPath('errors.0', 'Baris 3: Jenis peminjam "alien" tidak dikenal (pilihan: Mahasiswa, Tendik, Dosen, Umum).');

        $this->assertDatabaseMissing('students', ['student_id' => '12345']);
    }

    public function test_spreadsheet_lama_tanpa_kolom_jenis_tetap_diimpor(): void
    {
        Sanctum::actingAs($this->staff());

        // Spreadsheet lama hanya punya empat kolom awal.
        $csv = "NIM/NIP,Nama,Email,No. Telepon\n"
            ."2211082001,Budi Santoso,budi@example.com,0812\n";

        $this->post('/api/students/import', [
            'file' => UploadedFile::fake()->createWithContent('lama.csv', $csv),
        ], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('imported', 1);

        $this->assertDatabaseHas('students', [
            'student_id' => '2211082001',
            'type' => 'mahasiswa',
        ]);
    }

    public function test_template_impor_dibuat_untuk_tiap_kelompok(): void
    {
        Sanctum::actingAs($this->staff());

        // Mahasiswa punya template sendiri. Tendik & Dosen memakai satu
        // template gabungan dengan Role bebas dan NIP opsional.
        $expected = [
            'mahasiswa' => ['title' => 'TEMPLATE IMPOR DATA MAHASISWA', 'identity' => '>NIM<'],
            'tendik' => ['title' => 'TEMPLATE IMPOR DATA TENDIK & DOSEN', 'identity' => '>NIP (opsional)<'],
            'dosen' => ['title' => 'TEMPLATE IMPOR DATA TENDIK & DOSEN', 'identity' => '>NIP (opsional)<'],
        ];

        foreach ($expected as $type => $expect) {
            $shared = $type !== 'mahasiswa';
            $filename = $shared ? 'tendik-dosen' : $type;
            $response = $this->getJson('/api/students/import/template?type='.$type);

            $response->assertOk()
                ->assertHeader('Content-Disposition', 'attachment; filename="template-impor-'.$filename.'.xls"');

            $content = $response->getContent();
            $this->assertStringContainsString($expect['title'], $content, "judul template {$type}");
            $this->assertStringContainsString($expect['identity'], $content, "kolom identitas {$type}");
            $this->assertStringContainsString('Jabatan / Unit Kerja', $content);
            $this->assertStringContainsString('Email', $content);
            $this->assertStringContainsString('No. Telepon', $content);

            if ($shared) {
                $this->assertStringContainsString('">Role</td>', $content, "kolom Role pada template {$type}");
                $this->assertStringContainsString('background-color:#0e7490;', $content);
                $this->assertStringContainsString('Rumah Tangga', $content);
            } else {
                // Template mahasiswa tidak punya kolom Role karena jenisnya
                // sudah pasti.
                $this->assertStringNotContainsString('>Role</td>', $content);
            }

            // Tetap tanpa data contoh supaya tidak ikut terimpor.
            $this->assertStringNotContainsString('Budi', $content);
        }
    }

    public function test_template_impor_tersusun_rapi_dan_menunjuk_baris_data_yang_benar(): void
    {
        Sanctum::actingAs($this->staff());

        foreach (['mahasiswa', 'tendik', 'dosen'] as $type) {
            $content = $this->getJson('/api/students/import/template?type='.$type)->assertOk()->getContent();

            // Panduan pengisian berada SEBELUM header, bukan sebagai baris
            // data di bawahnya.
            $instructionPos = strpos($content, 'PANDUAN PENGISIAN');
            $headerPos = strpos($content, '>Nama<');
            $this->assertNotFalse($instructionPos, "blok panduan ada di template {$type}");
            $this->assertNotFalse($headerPos, "header kolom ada di template {$type}");
            $this->assertLessThan($headerPos, $instructionPos, "panduan mendahului header pada {$type}");

            // Baris data yang disebut pada petunjuk harus benar-benar berada
            // tepat di bawah baris header. Hitung baris dari urutan <tr>.
            preg_match('/Masukkan data mulai baris (\d+)/', $content, $matches);
            $this->assertNotEmpty($matches, "petunjuk menyebut nomor baris pada {$type}");
            $expectedFirstDataRow = (int) $matches[1];

            $rowsBeforeHeader = substr_count(substr($content, 0, $headerPos), '<tr');
            $this->assertSame(
                $rowsBeforeHeader + 2,
                $expectedFirstDataRow,
                "nomor baris data pada petunjuk {$type} cocok dengan posisi header",
            );
        }
    }

    public function test_template_gabungan_menggunakan_role_dan_tidak_mengulang_petunjuknya(): void
    {
        Sanctum::actingAs($this->staff());

        $content = $this->getJson('/api/students/import/template?type=tendik')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('>Role</b> opsional', $content);
        $this->assertStringContainsString('Rumah Tangga', $content);
        $this->assertStringContainsString('opsional', $content);
        $this->assertStringContainsString('tidak menentukan kategori', $content);
        $this->assertStringContainsString('selain itu masuk Tendik', $content);
        $this->assertStringContainsString('NIP (opsional)', $content);
        $this->assertStringContainsString('background-color:#0e7490;', $content);
    }

    public function test_setiap_jenis_punya_url_spreadsheet_terpisah(): void
    {
        Sanctum::actingAs($this->staff());

        // Tanpa parameter sama sekali, yang dibaca tetap spreadsheet mahasiswa.
        $this->getJson('/api/students/import/source')
            ->assertOk()
            ->assertJsonPath('type', 'mahasiswa')
            ->assertJsonPath('supported', true)
            ->assertJsonPath('url', '');

        // Mahasiswa memakai spreadsheetnya sendiri dan TIDAK boleh ikut
        // tersentuh oleh URL kelompok pegawai.
        $this->postJson('/api/students/import/source', [
            'url' => 'https://docs.google.com/spreadsheets/d/MHS/edit#gid=0',
            'type' => 'mahasiswa',
        ])->assertOk();

        $this->getJson('/api/students/import/source?type=mahasiswa')
            ->assertOk()
            ->assertJsonPath('url', 'https://docs.google.com/spreadsheets/d/MHS/edit#gid=0');

        // Tendik & Dosen memakai spreadsheet yang sama, sehingga menautkan
        // salah satu langsung berlaku untuk keduanya.
        $this->postJson('/api/students/import/source', [
            'url' => 'https://docs.google.com/spreadsheets/d/PEGAWAI/edit#gid=0',
            'type' => 'tendik',
        ])->assertOk()->assertJsonPath('type', 'tendik');

        $this->postJson('/api/students/import/source', [
            'url' => 'https://docs.google.com/spreadsheets/d/PEGAWAI/edit#gid=0',
            'type' => 'dosen',
        ])->assertOk();

        foreach (['tendik', 'dosen'] as $type) {
            $this->getJson('/api/students/import/source?type='.$type)
                ->assertOk()
                ->assertJsonPath('url', 'https://docs.google.com/spreadsheets/d/PEGAWAI/edit#gid=0');
        }

        // Sheet pegawai tidak boleh menimpa spreadsheet mahasiswa.
        $this->getJson('/api/students/import/source?type=mahasiswa')
            ->assertOk()
            ->assertJsonPath('url', 'https://docs.google.com/spreadsheets/d/MHS/edit#gid=0');

        // Peminjam umum tidak memakai sinkronisasi spreadsheet.
        $this->getJson('/api/students/import/source?type=umum')
            ->assertOk()
            ->assertJsonPath('supported', false)
            ->assertJsonPath('url', '');
        $this->postJson('/api/students/import/source', [
            'url' => 'https://docs.google.com/spreadsheets/d/UMUM/edit#gid=0',
            'type' => 'umum',
        ])->assertStatus(422);
    }

    public function test_spreadsheet_pegawai_mengizinkan_role_kosong(): void
    {
        Sanctum::actingAs($this->staff());

        // Role kosong tetap diimpor sebagai Tendik karena Jabatan / Unit Kerja
        // tidak memuat "Dosen".
        $csv = "NIM/NIP,Nama,Role,Jabatan / Unit Kerja,Email\n"
            ."198001012005011001,Siti Aminah,,Rumah Tangga,siti@pnp.ac.id\n"
            ."198001012005011002,Rahmat,Tendik,Staf Administrasi,rahmat@pnp.ac.id\n";

        $response = $this->post('/api/students/import', [
            'file' => UploadedFile::fake()->createWithContent('pegawai.csv', $csv),
            'type' => 'tendik',
        ], ['Accept' => 'application/json'])->assertOk();

        $response->assertJsonPath('imported', 2)
            ->assertJsonPath('errors', []);

        $this->assertDatabaseHas('students', [
            'student_id' => '198001012005011001',
            'type' => 'tendik',
            'role' => null,
        ]);
        $this->assertDatabaseHas('students', ['student_id' => '198001012005011002', 'type' => 'tendik']);
    }

    public function test_spreadsheet_dosen_menggunakan_jabatan_di_sheet_bersama(): void
    {
        Sanctum::actingAs($this->staff());

        // Jabatan / Unit Kerja menentukan kategori, bukan Role.
        $csv = "NIM/NIP,Nama,Role,Jabatan / Unit Kerja,Email\n"
            ."197505052000031002,Andi Saputra,Tendik,Dosen/D3 Sistem Informasi,andi@pnp.ac.id\n";

        $this->post('/api/students/import', [
            'file' => UploadedFile::fake()->createWithContent('dosen.csv', $csv),
            'type' => 'dosen',
        ], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('imported', 1);

        $this->assertDatabaseHas('students', ['student_id' => '197505052000031002', 'type' => 'dosen']);
    }

    public function test_spreadsheet_gabungan_menolak_role_mahasiswa(): void
    {
        Sanctum::actingAs($this->staff());

        // Sheet Tendik & Dosen tidak boleh dipakai memasukkan data mahasiswa.
        $csv = "NIM/NIP,Nama,Role,Jabatan / Unit Kerja,Email\n"
            ."2211082001,Budi Santoso,mahasiswa,Mahasiswa D3,budi@example.com\n"
            ."197505052000031002,Andi Saputra,Tendik,Dosen/D3 Sistem Informasi,andi@pnp.ac.id\n";

        $response = $this->post('/api/students/import', [
            'file' => UploadedFile::fake()->createWithContent('pegawai.csv', $csv),
            'type' => 'tendik',
        ], ['Accept' => 'application/json'])->assertOk();

        $response->assertJsonPath('imported', 1);
        $this->assertStringContainsString(
            'tidak boleh ada di spreadsheet',
            (string) $response->json('errors.0')
        );

        $this->assertDatabaseMissing('students', ['student_id' => '2211082001']);
        $this->assertDatabaseHas('students', ['student_id' => '197505052000031002', 'type' => 'dosen']);
    }

    public function test_daftar_peminjam_dapat_difilter_beberapa_jenis_sekaligus(): void
    {
        Sanctum::actingAs($this->staff());

        $seed = [
            ['mahasiswa', '2211082001', 'Mhs Satu'],
            ['mahasiswa', '2211082002', 'Mhs Dua'],
            ['tendik', '198001012005011001', 'Tendik Satu'],
            ['dosen', '197505052000031002', 'Dosen Satu'],
            ['umum', '081234567890', 'Umum Satu'],
        ];
        foreach ($seed as [$type, $id, $name]) {
            Student::create([
                'student_id' => $id,
                'name' => $name,
                'type' => $type,
                'email' => strtolower(str_replace(' ', '', $name)).'@pnp.ac.id',
            ]);
        }

        // Halaman Data Tendik/Dosen memakai "Semua" = tendik,dosen,umum.
        // Data mahasiswa TIDAK boleh ikut terbawa.
        $response = $this->getJson('/api/students?type=tendik,dosen,umum&per_page=10')->assertOk();

        $response->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.type', 'tendik,dosen,umum')
            ->assertJsonPath('meta.scope_total', 3);

        $this->assertSame(
            ['Dosen Satu', 'Tendik Satu', 'Umum Satu'],
            array_column($response->json('data'), 'name'),
        );

        // Angka per jenis tetap dihitung seluruhnya (untuk badge tab lain),
        // sedangkan `scope_total` hanya kelompok yang diminta.
        $response->assertJsonPath('meta.by_type.mahasiswa', 2)
            ->assertJsonPath('meta.by_type.all', 5);

        // Halaman Data Mahasiswa memakai "Semua" = mahasiswa saja.
        $this->getJson('/api/students?type=mahasiswa&per_page=10')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.scope_total', 2)
            ->assertJsonPath('meta.type', 'mahasiswa');
    }

    public function test_jenis_pada_filter_gabungan_tetap_divalidasi(): void
    {
        Sanctum::actingAs($this->staff());

        $this->getJson('/api/students?type=tendik,alien&per_page=10')
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    public function test_sinkronisasi_spreadsheet_per_jenis_tidak_saling_menimpa(): void
    {
        Sanctum::actingAs($this->staff());

        // Sheet pegawai memakai kolom "Jenis" karena Tendik & Dosen disatukan.
        Http::fake([
            'docs.google.com/*' => Http::response(
                "NIM/NIP,Nama,Role,Jabatan / Unit Kerja,Email\n"
                ."197505052000031002,Andi Saputra,Tendik,Dosen/D3 Sistem Informasi,andi@pnp.ac.id\n"
                ."198001012005011001,Siti Aminah,Dosen,Rumah Tangga,siti@pnp.ac.id\n",
                200
            ),
        ]);

        $this->postJson('/api/students/import/csv-url', [
            'url' => 'https://docs.google.com/spreadsheets/d/PEGAWAI/edit#gid=0',
            'type' => 'dosen',
        ])->assertOk()
            ->assertJsonPath('imported', 2)
            ->assertJsonPath('updated', 0);

        // Satu sheet memuat dua jenis sekaligus.
        $this->assertDatabaseHas('students', ['student_id' => '197505052000031002', 'type' => 'dosen']);
        $this->assertDatabaseHas('students', ['student_id' => '198001012005011001', 'type' => 'tendik']);

        // URL & waktu sinkron dipakai bersama Tendik & Dosen.
        $dosenSource = $this->getJson('/api/students/import/source?type=dosen')->assertOk();
        $dosenSource->assertJsonPath('url', 'https://docs.google.com/spreadsheets/d/PEGAWAI/edit#gid=0');
        $this->assertNotNull($dosenSource->json('last_synced_at'));

        // Tendik melihat sheet yang sama karena memang spreadsheet-nya sama.
        $this->getJson('/api/students/import/source?type=tendik')
            ->assertOk()
            ->assertJsonPath('url', 'https://docs.google.com/spreadsheets/d/PEGAWAI/edit#gid=0');

        // Spreadsheet mahasiswa TIDAK ikut tersentuh oleh sheet pegawai.
        $this->getJson('/api/students/import/source?type=mahasiswa')
            ->assertOk()
            ->assertJsonPath('url', '')
            ->assertJsonPath('last_synced_at', null);

        // Sinkron ulang tidak menggandakan data.
        $this->postJson('/api/students/import/csv-url', [
            'url' => 'https://docs.google.com/spreadsheets/d/PEGAWAI/edit#gid=0',
            'type' => 'tendik',
        ])->assertOk()
            ->assertJsonPath('imported', 0)
            ->assertJsonPath('unchanged', 2);
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
        $this->assertStringContainsString('NIM', $content);
        $this->assertStringContainsString('Nama', $content);
        $this->assertStringContainsString('Email', $content);
        $this->assertStringContainsString('No. Telepon', $content);
        // Kolom pendukung: jabatan/unit kerja.
        $this->assertStringContainsString('Jabatan / Unit Kerja', $content);

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
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([
            ['NIM/NIP', 'Nama', 'Email', 'No. Telepon'],
            ['2211082001', 'Budi Santoso', 'budi@example.com', '081234567890'],
            ['2211082002', 'Siti Aminah', 'siti@example.com', null],
        ], null, 'A1');
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);
        $spreadsheet->disconnectWorksheets();

        $response = $this->post('/api/students/import', [
            'file' => new UploadedFile($path, 'mahasiswa.xlsx', null, null, true),
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
            'file' => UploadedFile::fake()->createWithContent('data.pdf', '%PDF-1.4 dummy'),
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

    private function fakeCsvUpload(string $content, string $filename): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($filename, $content);
    }
}
