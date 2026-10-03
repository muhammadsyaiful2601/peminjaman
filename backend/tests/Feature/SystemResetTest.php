<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Item;
use App\Models\Student;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SystemResetTest extends TestCase
{
    use RefreshDatabase;

    private string $backupFilename;

    protected function setUp(): void
    {
        parent::setUp();

        $this->backupFilename = '';
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        if ($this->backupFilename !== '') {
            $path = storage_path('app/backups/'.$this->backupFilename);
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_admin_reset_membuat_backup_menghapus_data_operasional_dan_mempertahankan_konfigurasi(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'password' => Hash::make('rahasia')]);
        Sanctum::actingAs($admin);

        AppSetting::setValue('app_name', 'Nama aplikasi');
        Student::create([
            'student_id' => '198001',
            'name' => 'Pegawai Tes',
            'type' => 'tendik',
            'role' => 'Tendik',
            'email' => 'pegawai@example.com',
        ]);
        $itemId = DB::table('items')->insertGetId([
            'item_code' => 'BRG-01',
            'name' => 'Barang Tes',
            'category' => 'Tes',
            'stock' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $loanId = DB::table('loans')->insertGetId([
            'uuid' => '11111111-1111-4111-8111-111111111111',
            'loan_code' => 'PJM-TEST-01',
            'item_id' => $itemId,
            'qty' => 1,
            'borrower_name' => 'Peminjam Tes',
            'borrower_email' => 'peminjam@example.com',
            'status' => 'pending',
            'created_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('loan_items')->insert([
            'loan_id' => $loanId,
            'item_id' => $itemId,
            'qty' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('item_images')->insert([
            'item_id' => $itemId,
            'path' => 'items/gambar-tes.png',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('clearance_letters')->insert([
            'letter_date' => now()->toDateString(),
            'letter_number' => '001/TEST',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('technicians')->insert([
            'name' => 'Teknisi Tes',
            'nip' => '123456',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Storage::disk('public')->put('items/gambar-tes.png', 'test-image');
        Cache::put('system-reset-test', 'cached');

        $backup = $this->mock(BackupService::class);
        $backup->shouldReceive('createFullArchive')
            ->once()
            ->andReturnUsing(function (string $path): array {
                $this->backupFilename = basename($path);
                $this->assertDatabaseCount('students', 1);
                file_put_contents($path, 'backup contents');

                return [];
            });
        $backup->shouldReceive('isHybridAvailable')->andReturn(false);
        $backup->shouldReceive('markBackupCreated')->once();

        $response = $this->postJson('/api/system-reset', [
            'password' => 'rahasia',
            'confirmation' => 'RESET',
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('hybrid_reset', false)
            ->assertJsonPath('deleted.'.DB::getDefaultConnection().'.students', 1)
            ->assertJsonPath('deleted.'.DB::getDefaultConnection().'.items', 1)
            ->assertJsonPath('deleted.'.DB::getDefaultConnection().'.loans', 1);

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
        $this->assertDatabaseHas('app_settings', ['key' => 'app_name', 'value' => 'Nama aplikasi']);
        $this->assertDatabaseCount('students', 0);
        $this->assertDatabaseCount('items', 0);
        $this->assertDatabaseCount('loans', 0);
        $this->assertDatabaseCount('loan_items', 0);
        $this->assertDatabaseCount('item_images', 0);
        $this->assertDatabaseCount('clearance_letters', 0);
        $this->assertDatabaseCount('technicians', 0);
        $this->assertFalse(Cache::has('system-reset-test'));
        $this->assertFileExists(storage_path('app/backups/'.$this->backupFilename));

        $download = $this->getJson('/api/backups/reset-download/'.$this->backupFilename);
        $download->assertOk()->assertDownload($this->backupFilename);
    }

    public function test_password_salah_tidak_membuat_backup_atau_menghapus_data(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'password' => Hash::make('rahasia')]);
        Sanctum::actingAs($admin);
        Student::create([
            'student_id' => '198001',
            'name' => 'Pegawai Tes',
            'type' => 'tendik',
            'email' => 'pegawai@example.com',
        ]);

        $backup = $this->mock(BackupService::class);
        $backup->shouldNotReceive('createFullArchive');

        $this->postJson('/api/system-reset', [
            'password' => 'salah',
            'confirmation' => 'RESET',
        ])->assertStatus(422)
            ->assertJsonPath('ok', false);

        $this->assertDatabaseCount('students', 1);
    }

    public function test_reset_hanya_dapat_dijalankan_admin_dan_memerlukan_konfirmasi_teks(): void
    {
        $assistant = User::factory()->create(['role' => 'assistant']);
        Sanctum::actingAs($assistant);

        $this->postJson('/api/system-reset', [
            'password' => 'rahasia',
            'confirmation' => 'RESET',
        ])->assertForbidden();

        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $this->postJson('/api/system-reset', [
            'password' => 'rahasia',
            'confirmation' => 'reset',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('confirmation');
    }
}
