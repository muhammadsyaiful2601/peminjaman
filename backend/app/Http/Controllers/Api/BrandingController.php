<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Support\Branding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BrandingController extends Controller
{
    public function show()
    {
        return response()->json(['branding' => Branding::publicData()]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'app_name' => ['required', 'string', 'max:120'],
            'organization_ministry' => ['nullable', 'string', 'max:160'],
            'organization_name' => ['required', 'string', 'max:160'],
            'organization_unit' => ['nullable', 'string', 'max:160'],
            'organization_department' => ['nullable', 'string', 'max:160'],
            'organization_address' => ['nullable', 'string', 'max:255'],
            'login_description' => ['nullable', 'string', 'max:255'],
            'app_logo' => ['nullable', 'image', 'max:3072'],
            'landing_photo' => ['nullable', 'image', 'max:5120'],
            'letterhead_logo' => ['nullable', 'image', 'max:3072'],
        ]);

        foreach ([
            'app_logo' => 'app_logo_path',
            'landing_photo' => 'landing_photo_path',
            'letterhead_logo' => 'letterhead_logo_path',
        ] as $fileKey => $settingKey) {
            if ($request->hasFile($fileKey)) {
                $oldPath = AppSetting::getValue($settingKey);
                if ($oldPath) {
                    Storage::disk('public')->delete($oldPath);
                }
                $validated[$settingKey] = $request->file($fileKey)->store('branding', 'public');
            }
        }

        foreach (['app_name', 'organization_ministry', 'organization_name', 'organization_unit', 'organization_department', 'organization_address', 'login_description', 'app_logo_path', 'landing_photo_path', 'letterhead_logo_path'] as $key) {
            if (array_key_exists($key, $validated)) {
                AppSetting::setValue($key, $validated[$key]);
            }
        }

        return response()->json([
            'message' => 'Pengaturan sistem berhasil disimpan.',
            'branding' => Branding::publicData(),
        ]);
    }

    /**
     * Simpan nama & logo aplikasi dari wizard konfigurasi awal aplikasi desktop.
     *
     * Wizard first-run berjalan SEBELUM admin login, sehingga tidak bisa memakai
     * sesi Sanctum + role admin seperti update(). Route ini dilindungi middleware
     * desktop.key (header X-Desktop-Key) yang hanya diketahui jendela Electron.
     * Logo dikirim sebagai data URL base64 agar wizard tidak perlu menyusun
     * multipart/form-data.
     */
    public function desktopUpdate(Request $request)
    {
        $validated = $request->validate([
            'app_name' => ['nullable', 'string', 'max:120'],
            'app_logo_base64' => ['nullable', 'string'],
        ]);

        if (! empty($validated['app_name'])) {
            AppSetting::setValue('app_name', $validated['app_name']);
        }

        $logo = trim((string) ($validated['app_logo_base64'] ?? ''));

        if ($logo !== '') {
            $extension = null;
            $binary = self::decodeImageData($logo, $extension);

            if ($binary === null) {
                return response()->json([
                    'message' => 'Logo tidak dikenali. Gunakan gambar PNG, JPG, WEBP, atau SVG.',
                ], 422);
            }

            if (strlen($binary) > 3 * 1024 * 1024) {
                return response()->json([
                    'message' => 'Ukuran logo melebihi 3 MB. Pilih gambar yang lebih kecil.',
                ], 422);
            }

            $oldPath = AppSetting::getValue('app_logo_path');
            if ($oldPath) {
                Storage::disk('public')->delete($oldPath);
            }

            $path = 'branding/'.Str::random(40).'.'.$extension;
            Storage::disk('public')->put($path, $binary);
            AppSetting::setValue('app_logo_path', $path);
        }

        return response()->json([
            'message' => 'Nama dan logo aplikasi berhasil disimpan.',
            'branding' => Branding::publicData(),
        ]);
    }

    /**
     * Ubah data URL ("data:image/png;base64,...") maupun base64 mentah menjadi
     * byte gambar. Mengembalikan null bila bukan gambar yang didukung; ekstensi
     * berkas diisi melalui parameter referensi.
     */
    private static function decodeImageData(string $payload, ?string &$extension = null): ?string
    {
        $stripped = (string) preg_replace('#^data:[^;,]*;base64,#i', '', $payload);
        $base64 = (string) preg_replace('/\s+/', '', $stripped);
        $binary = $base64 === '' ? false : base64_decode($base64, true);

        if ($binary === false || $binary === '') {
            return null;
        }

        // SVG tidak dikenali getimagesizefromstring, jadi dideteksi manual.
        if (preg_match('#^(?:<\?xml[^>]*\?>\s*)?(?:<!--.*?-->\s*)*<svg#is', ltrim(substr($binary, 0, 512)))) {
            $extension = 'svg';

            return $binary;
        }

        $info = @getimagesizefromstring($binary);

        $extensions = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/bmp' => 'bmp',
        ];

        if (! is_array($info) || ! isset($extensions[$info['mime']])) {
            return null;
        }

        $extension = $extensions[$info['mime']];

        return $binary;
    }
}
