<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemRecipe extends Model
{
    protected $fillable = ['item_id', 'ingredient_id', 'qty_base'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'ingredient_id');
    }
}
