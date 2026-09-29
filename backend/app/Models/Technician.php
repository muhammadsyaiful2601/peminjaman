<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

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
}