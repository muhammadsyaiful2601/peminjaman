<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Support\Branding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Wizard konfigurasi awal aplikasi desktop: nama & logo aplikasi disimpan
 * lewat endpoint khusus desktop (X-Desktop-Key) karena berjalan sebelum login.
 */
class DesktopBrandingTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'test-desktop-key';

    /** PNG 1x1 piksel yang valid. */
    private const PNG_1PX = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFBQIAX8jx0gAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.desktop_key' => self::KEY]);
        Storage::fake('public');
    }

    private function withKey(): array
    {
        return ['X-Desktop-Key' => self::KEY];
    }

    public function test_endpoint_dilarang_tanpa_kunci_desktop(): void
    {
        $this->postJson('/api/desktop/branding', ['app_name' => 'Aplikasi Lain'])
            ->assertStatus(404);

        $this->postJson('/api/desktop/branding', ['app_name' => 'Aplikasi Lain'], ['X-Desktop-Key' => 'salah'])
            ->assertStatus(404);

        $this->assertNull(AppSetting::getValue('app_name'));
    }

    public function test_nama_aplikasi_tersimpan_dan_tampil_pada_branding_publik(): void
    {
        $this->postJson('/api/desktop/branding', [
            'app_name' => 'Sistem Peminjaman Laboratorium',
        ], $this->withKey())
            ->assertStatus(200)
            ->assertJsonPath('branding.app_name', 'Sistem Peminjaman Laboratorium');

        $this->assertSame('Sistem Peminjaman Laboratorium', AppSetting::getValue('app_name'));

        // Halaman login membaca data yang sama lewat endpoint publik.
        $this->getJson('/api/branding')
            ->assertStatus(200)
            ->assertJsonPath('branding.app_name', 'Sistem Peminjaman Laboratorium');
    }

    public function test_logo_png_disimpan_dari_data_url_dan_path_dikembalikan(): void
    {
        $response = $this->postJson('/api/desktop/branding', [
            'app_name' => 'Peminjaman Barang PNP',
            'app_logo_base64' => 'data:image/png;base64,'.self::PNG_1PX,
        ], $this->withKey());

        $response->assertStatus(200);

        $path = AppSetting::getValue('app_logo_path');
        $this->assertNotNull($path);
        $this->assertStringStartsWith('branding/', $path);
        $this->assertStringEndsWith('.png', $path);
        Storage::disk('public')->assertExists($path);

        // Branding publik memakai URL relatif /storage/... agar langsung bisa
        // dipakai halaman login (file://) maupun SPA.
        $this->assertSame('/storage/'.$path, $response->json('branding.app_logo_path'));
    }

    public function test_logo_svg_diterima_walau_bukan_gambar_raster(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><circle cx="5" cy="5" r="4"/></svg>';

        $this->postJson('/api/desktop/branding', [
            'app_logo_base64' => 'data:image/svg+xml;base64,'.base64_encode($svg),
        ], $this->withKey())->assertStatus(200);

        $path = AppSetting::getValue('app_logo_path');
        $this->assertStringEndsWith('.svg', $path);
        $this->assertSame($svg, Storage::disk('public')->get($path));
    }

    public function test_logo_lama_dihapus_saat_diganti(): void
    {
        $this->postJson('/api/desktop/branding', [
            'app_logo_base64' => 'data:image/png;base64,'.self::PNG_1PX,
        ], $this->withKey())->assertStatus(200);

        $first = AppSetting::getValue('app_logo_path');

        $this->postJson('/api/desktop/branding', [
            'app_logo_base64' => 'data:image/png;base64,'.self::PNG_1PX,
        ], $this->withKey())->assertStatus(200);

        $second = AppSetting::getValue('app_logo_path');

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    public function test_berkas_bukan_gambar_ditolak(): void
    {
        $this->postJson('/api/desktop/branding', [
            'app_logo_base64' => base64_encode('ini bukan gambar sama sekali'),
        ], $this->withKey())
            ->assertStatus(422)
            ->assertJsonPath('message', 'Logo tidak dikenali. Gunakan gambar PNG, JPG, WEBP, atau SVG.');

        $this->assertNull(AppSetting::getValue('app_logo_path'));
    }

    public function test_nama_aplikasi_terlalu_panjang_ditolak(): void
    {
        $this->postJson('/api/desktop/branding', [
            'app_name' => str_repeat('a', 121),
        ], $this->withKey())->assertStatus(422);

        $this->assertNull(AppSetting::getValue('app_name'));
    }

    public function test_tanpa_payload_tidak_mengubah_branding_yang_ada(): void
    {
        $this->postJson('/api/desktop/branding', [
            'app_name' => 'Nama Awal',
        ], $this->withKey())->assertStatus(200);

        $this->postJson('/api/desktop/branding', [], $this->withKey())->assertStatus(200);

        $this->assertSame('Nama Awal', AppSetting::getValue('app_name'));
        $this->assertSame('Nama Awal', Branding::publicData()['app_name']);
    }
}
