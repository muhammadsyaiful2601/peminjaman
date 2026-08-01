<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['item_code', 'name', 'category', 'stock', 'image'])]
class Item extends Model
{
    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }
}