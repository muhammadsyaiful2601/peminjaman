{{--
    Seluruh pilihan font PDF menggunakan keluarga bawaan Dompdf/Base 14.
    Helper tetap dipakai template agar seluruh dokumen menghormati pilihan font
    yang disimpan, tanpa membutuhkan berkas font eksternal.
--}}
{!! \App\Support\PdfFont::faceCss() !!}
