<?php

namespace App\Models;

use App\Support\BorrowerType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'student_id',
    'name',
    'type',
    'position',
    'email',
    'phone',
])]
class Student extends Model
{
    protected function casts(): array
    {
        return [
            'student_id' => 'string',
            'name' => 'string',
            'position' => 'string',
            'email' => 'string',
            'phone' => 'string',
        ];
    }

    /**
     * Jenis peminjam selalu dibaca sebagai salah satu dari
     * `BorrowerType::ALL`. Transaksi dan data lama yang belum punya jenis
     * dibaca sebagai "mahasiswa" supaya tidak salah tampil sebagai "Umum".
     */
    protected function type(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value) => BorrowerType::clean($value),
        );
    }

    /**
     * Label kolom identitas sesuai jenis peminjam: NIM untuk mahasiswa,
     * NIP untuk pegawai, dan "Nomor Identitas" untuk peminjam umum.
     */
    public function getIdentityLabelAttribute(): string
    {
        return match ($this->type) {
            BorrowerType::DOSEN, BorrowerType::TENDIK => 'NIP',
            BorrowerType::UMUM => 'Nomor Identitas',
            default => 'NIM',
        };
    }
}
