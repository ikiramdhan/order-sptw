<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustment extends Model
{
    protected $fillable = ['item_id', 'qty_system', 'qty_physical', 'difference', 'reason', 'notes', 'user_id'];

    public const REASONS = ['opname' => 'Stok opname', 'rusak' => 'Rusak', 'hilang' => 'Hilang', 'lainnya' => 'Lainnya'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
