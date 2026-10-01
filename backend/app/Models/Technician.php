<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'position', 'nip', 'whatsapp', 'signature_path', 'is_primary'])]
class Technician extends Model
{
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    /** URL tanda tangan digital untuk ditampilkan di halaman (null bila belum diunggah). */
    public function getSignatureUrlAttribute(): ?string
    {
        return $this->signature_path ? '/storage/'.$this->signature_path : null;
    }

    /**
     * Tanda tangan sebagai data URI (`data:image/png;base64,...`) untuk
     * disisipkan ke PDF dan HTML surat.
     *
     * Data URI dipilih (bukan path berkas) karena DomPDF hanya boleh membaca
     * berkas di dalam folder chroot-nya, sedangkan tanda tangan tersimpan di
     * `storage/app/public`. Dengan data URI, gambar tetap tampil di web
     * hosting maupun aplikasi desktop tanpa perlu mengatur chroot.
     *
     * Mengembalikan null bila belum ada tanda tangan, berkas hilang, atau
     * formatnya bukan gambar.
     */
    public function getSignatureDataUriAttribute(): ?string
    {
        if (! $this->signature_path) {
            return null;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($this->signature_path)) {
            return null;
        }

        $mime = (string) ($disk->mimeType($this->signature_path) ?: 'image/png');

        if (! in_array($mime, ['image/png', 'image/jpeg'], true)) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode($disk->get($this->signature_path));
    }
}