<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemUnit extends Model
{
    protected $fillable = ['item_id', 'unit_id', 'factor', 'is_sellable', 'is_purchasable'];

    protected function casts(): array
    {
        return ['is_sellable' => 'boolean', 'is_purchasable' => 'boolean'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
