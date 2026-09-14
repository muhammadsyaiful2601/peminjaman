<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemImage extends Model
{
    protected $fillable = ['item_id', 'path'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
