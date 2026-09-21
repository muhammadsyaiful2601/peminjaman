<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'student_id',
    'name',
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
            'email' => 'string',
            'phone' => 'string',
        ];
    }
}
