<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['item_code', 'name', 'category', 'stock', 'image'])]
class Item extends Model
{
    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function loanItems(): HasMany
    {
        return $this->hasMany(LoanItem::class);
    }

    public function multiItemLoans(): BelongsToMany
    {
        return $this->belongsToMany(Loan::class, 'loan_items')->withPivot('qty')->withTimestamps();
    }
}