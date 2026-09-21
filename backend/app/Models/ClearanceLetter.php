<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'letter_date',
    'letter_number',
])]
class ClearanceLetter extends Model
{
    protected function casts(): array
    {
        return [
            'letter_date' => 'date',
        ];
    }
}
