<?php

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Storage;

class Branding
{
    public const DEFAULTS = [
        'app_name' => 'Sistem Peminjaman Barang',
        'organization_ministry' => 'KEMENTERIAN PENDIDIKAN DAN KEBUDAYAAN',
        'organization_name' => 'POLITEKNIK NEGERI PADANG',
        'organization_unit' => 'PROGRAM STUDI DILUAR KAMPUS UTAMA',
        'organization_department' => 'PRODI D-3 SISTEM INFORMASI',
        'organization_address' => 'Jl. Raya Tigo Jangko Kec. Lintau Buo - 27292',
        'login_description' => 'Kelola peminjaman, verifikasi foto dan QR, serta pantau stok barang dalam satu tempat.',
        'app_logo_path' => null,
        'landing_photo_path' => null,
        'letterhead_logo_path' => null,
    ];

    public static function all(): array
    {
        $values = AppSetting::query()->pluck('value', 'key')->all();

        return array_merge(self::DEFAULTS, $values);
    }

    public static function publicData(): array
    {
        $branding = self::all();

        foreach (['app_logo_path', 'landing_photo_path', 'letterhead_logo_path'] as $key) {
            $branding[$key] = self::publicUrl($branding[$key]);
        }

        return $branding;
    }

    public static function pdfData(): array
    {
        $branding = self::all();

        foreach (['app_logo_path', 'letterhead_logo_path'] as $key) {
            $path = $branding[$key];
            $branding[$key] = $path && Storage::disk('public')->exists($path)
                ? Storage::disk('public')->path($path)
                : public_path('images/logo_kampus.png');
        }

        return $branding;
    }

    public static function publicUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return '/storage/'.$path;
    }
}
