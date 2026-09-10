<?php

namespace Tests\Feature;

use App\Support\Hybrid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HybridSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'test-desktop-key';

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.desktop_key' => self::KEY]);
    }

    protected function tearDown(): void
    {
        Hybrid::forgetConfig();
        parent::tearDown();
    }

    private function withKey(): array
    {
        return ['X-Desktop-Key' => self::KEY];
    }

    public function test_status_endpoint_dilarang_tanpa_kunci_desktop(): void
    {
        $this->getJson('/api/hybrid/status')->assertStatus(404);

        $this->getJson('/api/hybrid/status', ['X-Desktop-Key' => 'salah'])
            ->assertStatus(404);
    }

    public function test_status_mengembalikan_default_ketika_belum_dikonfigurasi(): void
    {
        $response = $this->getJson('/api/hybrid/status', $this->withKey());

        $response->assertStatus(200)
            ->assertJson([
                'configured' => false,
                'enabled' => false,
                'due' => false,
                'host' => '',
                'database' => '',
            ]);

        // Password tidak boleh pernah bocor dalam bentuk apa pun.
        $this->assertArrayNotHasKey('password', $response->json());
    }

    public function test_simpan_konfigurasi_tersimpan_meski_host_tak_terjangkau(): void
    {
        $response = $this->postJson('/api/hybrid/config', [
            'host' => '127.0.0.1',
            'port' => 1, // port tak terpakai -> koneksi pasti gagal
            'database' => 'u123_peminjaman',
            'username' => 'u123_admin',
            'password' => 'rahasia',
        ], $this->withKey());

        $response->assertStatus(200)
            ->assertJson(['ok' => true, 'test_ok' => false]);

        $config = Hybrid::readConfig();
        $this->assertNotNull($config);
        $this->assertSame('127.0.0.1', $config['host']);
        $this->assertSame('u123_peminjaman', $config['database']);
        $this->assertSame('rahasia', $config['password']);

        // Status tidak mengungkap password.
        $status = $this->getJson('/api/hybrid/status', $this->withKey())->json();
        $this->assertTrue($status['configured']);
        $this->assertTrue($status['has_password']);
        $this->assertArrayNotHasKey('password', $status);
    }

    public function test_aktifkan_otomatis_gagal_bila_konfigurasi_belum_ada(): void
    {
        $this->postJson('/api/hybrid/toggle', ['enabled' => true], $this->withKey())
            ->assertStatus(422);
    }

    public function test_aktifkan_otomatis_berhasil_setelah_konfigurasi_ada(): void
    {
        $this->postJson('/api/hybrid/config', [
            'host' => '127.0.0.1',
            'port' => 1,
            'database' => 'u123_peminjaman',
            'username' => 'u123_admin',
        ], $this->withKey())->assertStatus(200);

        $this->postJson('/api/hybrid/toggle', ['enabled' => true], $this->withKey())
            ->assertStatus(200)
            ->assertJson(['ok' => true, 'enabled' => true]);

        $this->assertTrue(Hybrid::readConfig()['enabled']);
    }

    public function test_sinkronisasi_berkala_dilewati_bila_tidak_jatuh_tempo(): void
    {
        $this->artisan('hybrid:sync', ['--due' => true])
            ->expectsOutput('Sinkronisasi otomatis tidak jatuh tempo / tidak aktif.')
            ->assertSuccessful();
    }

    public function test_sinkronisasi_manual_gagal_jelas_bila_konfigurasi_kosong(): void
    {
        $this->artisan('hybrid:sync')
            ->expectsOutput('Konfigurasi hosting belum diisi.')
            ->assertExitCode(1);
    }

    public function test_migrasi_gagal_jelas_bila_konfigurasi_kosong(): void
    {
        $this->postJson('/api/hybrid/migrate', [], $this->withKey())
            ->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'Konfigurasi hosting belum diisi.');
    }

    public function test_is_due_aktif_setelah_lebih_tujuh_hari(): void
    {
        Hybrid::writeConfig([
            'enabled' => true,
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'u123_peminjaman',
            'username' => 'u123_admin',
            'password' => 'rahasia',
            'last_sync_at' => now()->subDays(8)->toIso8601String(),
            'last_sync_ok' => true,
            'last_sync_message' => null,
        ]);

        $this->assertTrue(Hybrid::isDue());

        Hybrid::writeConfig(array_merge(Hybrid::readConfig(), [
            'last_sync_at' => now()->subDays(2)->toIso8601String(),
        ]));

        $this->assertFalse(Hybrid::isDue());
    }
}
