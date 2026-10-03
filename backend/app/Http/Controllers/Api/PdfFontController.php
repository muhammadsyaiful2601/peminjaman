<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\PdfFont;
use Illuminate\Http\Request;

/**
 * Pemilihan font untuk dokumen PDF.
 *
 * Rute terpisah dari BrandingController karena daftar font bukan data branding
 * (tidak ikut disalin ke frontend), melainkan katalog hasil pembacaan berkas
 * font di server.
 */
class PdfFontController extends Controller
{
    /**
     * Daftar font yang bisa dipilih beserta font yang sedang aktif.
     */
    public function index()
    {
        return response()->json([
            'current' => PdfFont::currentKey(),
            'fonts' => PdfFont::options(),
        ]);
    }

    /**
     * Simpan pilihan font untuk seluruh dokumen PDF.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'pdf_font' => ['required', 'string', 'max:60'],
        ]);

        if (! PdfFont::select($validated['pdf_font'])) {
            return response()->json([
                'message' => 'Font PDF tidak dikenal atau berkas font-nya belum tersedia.',
                'fonts' => PdfFont::options(),
            ], 422);
        }

        return response()->json([
            'message' => 'Font PDF berhasil diperbarui.',
            'current' => PdfFont::currentKey(),
            'fonts' => PdfFont::options(),
        ]);
    }
}
