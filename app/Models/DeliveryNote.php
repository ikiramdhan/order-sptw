<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DeliveryNote extends Model
{
    protected $fillable = ['number', 'purchase_order_id', 'outlet_id', 'type', 'return_id', 'status', 'created_by', 'shipped_at',
        'courier_id', 'courier_status', 'courier_started_at', 'courier_arrived_at', 'last_lat', 'last_lng', 'last_accuracy', 'last_loc_at'];

    protected function casts(): array
    {
        return ['shipped_at' => 'datetime', 'courier_started_at' => 'datetime', 'courier_arrived_at' => 'datetime', 'last_loc_at' => 'datetime', 'last_lat' => 'float', 'last_lng' => 'float'];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'courier_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryNoteItem::class)->with(['item.baseUnit', 'unit']);
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(GoodsReceipt::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }
}
