<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $fillable = ['item_id', 'type', 'qty_base', 'balance_after', 'ref_type', 'ref_id', 'user_id', 'notes'];

    public const LABELS = [
        'pembelian' => 'Pembelian supplier',
        'penjualan' => 'Pengiriman ke outlet',
        'retur_masuk' => 'Retur masuk stok',
        'retur_pengganti' => 'Barang pengganti retur',
        'penyesuaian' => 'Penyesuaian',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
