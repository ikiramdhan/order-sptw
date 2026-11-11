<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutletChargeItem extends Model
{
    protected $fillable = ['outlet_charge_id', 'stock_request_id', 'item_id', 'kind', 'description', 'qty_base', 'unit_price', 'value'];

    public function item(): BelongsTo { return $this->belongsTo(Item::class); }
}
