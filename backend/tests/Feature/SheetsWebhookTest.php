<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Student;
use App\Models\User;
use App\Support\BorrowerType;
use App\Support\SheetsWebhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tulis balik Jabatan / Unit Kerja ke Google Sheets.
 *
 * CSV yang dipublikasikan Google Sheets hanya-baca, jadi perubahan jabatan di
 * aplikasi dikirim lewat Google Apps Script (Web App). Test ini menjaga:
 * konfigurasi webhook tersimpan per jenis, hanya host Google yang diterima,
 * jabatan terkirim saat data diperbarui, dan kegagalan webhook tidak
 * membatalkan penyimpanan lokal.
 */
class SheetsWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK = 'https://script.google.com/macros/s/AKfycbTEST/exec';

    private function staff(string $role = 'assistant'): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function tendik(array $attributes = []): Student
    {
        return Student::create(array_merge([
            'student_id' => '198001012005011001',
            'name' => 'Andi Saputra',
            'type' => BorrowerType::TENDIK,
            'position' => null,
            'email' => 'andi@pnp.ac.id',
            'phone' => '081234567890',
        ], $attributes));
    }

    public function test_url_webhook_apps_script_dikenali(): void
    {
        $this->assertTrue(SheetsWebhook::isValidUrl(self::WEBHOOK));
        $this->assertTrue(SheetsWebhook::isValidUrl('https://script.google.com/macros/s/AKfycb-x_9/exec'));
    }

    public function test_url_di_luar_google_apps_script_ditolak(): void
    {
        // Mencegah app_settings dipakai memanggil alamat arbitrer dari server.
        $this->assertFalse(SheetsWebhook::isValidUrl('https://contoh-penol.webhook.site/abc'));
        $this->assertFalse(SheetsWebhook::isValidUrl('http://script.google.com/macros/s/ABC/exec'));
        $this->assertFalse(SheetsWebhook::isValidUrl('https://script.google.com/beranda'));
    }

    public function test_url_webhook_bersih_dari_query_google(): void
    {
        $this->assertSame(
            self::WEBHOOK,
            SheetsWebhook::cleanUrl('  '.self::WEBHOOK.'?usp=sharing  ')
        );
    }

    public function test_petugas_bisa_menyimpan_url_webhook(): void
    {
        Sanctum::actingAs($this->staff());

        $this->postJson('/api/students/webhook', ['type' => 'tendik', 'url' => self::WEBHOOK])
            ->assertOk()
            ->assertJsonPath('webhook_url', self::WEBHOOK);

        // Tendik & Dosen memakai satu kunci webhook bersama karena spreadsheetnya
        // juga satu. Mahasiswa tetap memakai kuncinya sendiri.
        $this->assertSame(self::WEBHOOK, AppSetting::getValue('employee_sheets_webhook_url'));
        $this->assertSame(self::WEBHOOK, BorrowerType::webhookUrl('tendik'));
        $this->assertSame(self::WEBHOOK, BorrowerType::webhookUrl('dosen'));

        // Mahasiswa memakai sheet sendiri sehingga tidak ikut terpengaruh.
        $this->assertSame('', BorrowerType::webhookUrl('mahasiswa'));
    }

    public function test_url_webhook_tidak_sah_ditolak_dengan_pesan(): void
    {
        Sanctum::actingAs($this->staff());

        $this->postJson('/api/students/webhook', ['type' => 'tendik', 'url' => 'https://contoh-penol.webhook.site/abc'])
            ->assertStatus(422);

        $this->assertNull(AppSetting::getValue('employee_sheets_webhook_url'));
    }

    public function test_url_kosong_mematikan_webhook(): void
    {
        Sanctum::actingAs($this->staff());
        AppSetting::setValue('employee_sheets_webhook_url', self::WEBHOOK);

        $response = $this->postJson('/api/students/webhook', ['type' => 'tendik', 'url' => '']);
        $response->assertOk();
        $this->assertSame('', BorrowerType::webhookUrl('tendik'));
    }

    public function test_admin_dan_asisten_boleh_mengatur_webhook(): void
    {
        // Route memakai `role:admin,assistant` — petugas utama maupun asisten
        // boleh mengisi URL, karena keduanya juga boleh mengedit data peminjam.
        Sanctum::actingAs($this->staff('admin'));
        $this->postJson('/api/students/webhook', ['type' => 'tendik', 'url' => self::WEBHOOK])->assertOk();
        $this->assertSame(self::WEBHOOK, BorrowerType::webhookUrl('tendik'));
    }

    public function test_status_sumber_menyertakan_url_webhook(): void
    {
        Sanctum::actingAs($this->staff());
        AppSetting::setValue('employee_sheets_webhook_url', self::WEBHOOK);

        $this->getJson('/api/students/import/source?type=tendik')
            ->assertOk()
            ->assertJsonPath('webhook_url', self::WEBHOOK);
    }

    public function test_mengubah_jabatan_mengirimnya_ke_spreadsheet(): void
    {
        Sanctum::actingAs($this->staff());
        AppSetting::setValue('employee_sheets_webhook_url', self::WEBHOOK);

        $student = $this->tendik();

        Http::fake(['script.google.com/*' => Http::response('{"ok":true}', 200)]);

        $this->putJson('/api/students/'.$student->id, [
            'student_id' => $student->student_id,
            'name' => $student->name,
            'type' => 'tendik',
            'role' => 'Rumah Tangga',
            'position' => 'Staf Bagian Keuangan',
            'email' => $student->email,
            'phone' => $student->phone,
        ])->assertOk()->assertJsonPath('spreadsheet.ok', true);

        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'role' => 'Rumah Tangga',
            'position' => 'Staf Bagian Keuangan',
        ]);

        // Jabatan hasil edit wajib ikut terkirim, bukan hanya NIP dan nama.
        Http::assertSent(function ($request) {
            $body = $request->data();

            return $request->url() === self::WEBHOOK
                && ($body['identity'] ?? null) === '198001012005011001'
                && ($body['columns']['Role'] ?? null) === 'Rumah Tangga'
                && ($body['columns']['Jabatan / Unit Kerja'] ?? null) === 'Staf Bagian Keuangan'
                && ($body['columns']['NIP'] ?? null) === '198001012005011001';
        });

        // Waktu penulisan terakhir dicatat agar petugas bisa memverifikasi.
        $this->assertNotNull(AppSetting::getValue('employee_sheets_push_last_at'));
    }

    public function test_jabatan_tidak_dikirim_bila_webhook_belum_diatur(): void
    {
        Sanctum::actingAs($this->staff());
        $student = $this->tendik();

        Http::fake();

        $this->putJson('/api/students/'.$student->id, [
            'student_id' => $student->student_id,
            'name' => $student->name,
            'type' => 'tendik',
            'position' => 'Asisten Lab Komputer',
            'email' => $student->email,
        ])->assertOk()->assertJsonPath('spreadsheet.skipped', true);

        // Data tetap tersimpan walau spreadsheet tidak dikonfigurasi.
        $this->assertDatabaseHas('students', ['id' => $student->id, 'position' => 'Asisten Lab Komputer']);
        Http::assertNothingSent();
    }

    public function test_kegagalan_webhook_tidak_membatalkan_penyimpanan(): void
    {
        Sanctum::actingAs($this->staff());
        AppSetting::setValue('employee_sheets_webhook_url', self::WEBHOOK);

        $dosen = Student::create([
            'student_id' => '197505052000031002',
            'name' => 'Siti Rahayu',
            'type' => BorrowerType::DOSEN,
            'email' => 'siti@pnp.ac.id',
        ]);

        Http::fake(['script.google.com/*' => Http::response('Internal Error', 500)]);

        $response = $this->putJson('/api/students/'.$dosen->id, [
            'student_id' => $dosen->student_id,
            'name' => $dosen->name,
            'type' => 'dosen',
            'position' => 'Dosen Teknik Informatika',
            'email' => $dosen->email,
        ])->assertOk();

        // Spreadsheet adalah pelengkap: kegagalan tidak boleh membuat petugas
        // mengira data peminjamannya gagal disimpan.
        $this->assertDatabaseHas('students', ['id' => $dosen->id, 'position' => 'Dosen Teknik Informatika']);
        $this->assertFalse($response->json('spreadsheet.ok'));
        $this->assertStringContainsString('gagal dikirim ke spreadsheet', $response->json('spreadsheet.message'));
        $this->assertNull(AppSetting::getValue('employee_sheets_push_last_at'));
    }

    public function test_uji_webhook_mengirim_baris_contoh(): void
    {
        Sanctum::actingAs($this->staff());
        AppSetting::setValue('employee_sheets_webhook_url', self::WEBHOOK);
        $this->tendik();

        Http::fake(['script.google.com/*' => Http::response('{"ok":true}', 200)]);

        $this->postJson('/api/students/webhook/test', ['type' => 'tendik'])
            ->assertOk()
            ->assertJsonPath('ok', true);

        Http::assertSent(fn ($request) => $request->url() === self::WEBHOOK);
    }

    public function test_uji_webhook_gagal_bila_belum_dikonfigurasi(): void
    {
        Sanctum::actingAs($this->staff());
        $this->tendik();

        $this->postJson('/api/students/webhook/test', ['type' => 'tendik'])
            ->assertStatus(422)
            ->assertJsonPath('ok', false);
    }

    public function test_endpoint_webhook_memerlukan_login(): void
    {
        $this->postJson('/api/students/webhook', ['type' => 'tendik', 'url' => self::WEBHOOK])
            ->assertUnauthorized();
    }
}
