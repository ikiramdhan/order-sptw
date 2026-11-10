<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id', 'item_id', 'unit_id', 'qty_input', 'qty_base', 'qty_shipped_base', 'qty_cancelled_base', 'unit_price_base',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** Sisa yang belum dikirim dan belum dibatalkan (satuan dasar). */
    public function remainingBase(): float
    {
        return max(0.0, (float) $this->qty_base - (float) $this->qty_shipped_base - (float) $this->qty_cancelled_base);
    }

    public function factor(): float
    {
        return $this->item->factorFor($this->unit_id);
    }
}
