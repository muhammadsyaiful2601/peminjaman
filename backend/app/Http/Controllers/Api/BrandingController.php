<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Support\Branding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
}
