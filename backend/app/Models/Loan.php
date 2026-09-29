<?php

namespace App\Models;

use App\Support\BorrowerType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'uuid',
    'qr_token',
    'loan_code',
    'item_id',
    'qty',
    'borrower_name',
    'borrower_email',
    'borrower_phone',
    'borrower_student_id',
    'borrower_type',
    'borrow_photo',
    'status',
    'borrowed_at',
    'returned_at',
    'created_by',
    'verified_by',
    'condition_on_return',
    'return_photo',
])]
class Loan extends Model
{
    protected function casts(): array
    {
        return [
            'borrowed_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }

    /**
     * Jenis peminjam saat transaksi dibuat. Transaksi lama tidak punya kolom
     * ini, jadi null dibaca sebagai "mahasiswa" (semula satu-satunya jenis).
     */
    protected function borrowerType(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value) => BorrowerType::clean($value),
        );
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function loanItems(): HasMany
    {
        return $this->hasMany(LoanItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}