<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    protected $fillable = [
        'number', 'outlet_id', 'created_by', 'status', 'notes', 'submitted_at', 'approved_at', 'approved_by', 'cancel_reason',
    ];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'approved_at' => 'datetime'];
    }

    /** status => [label, warna badge] */
    public const STATUS = [
        'draft' => ['Draft', 'info'],
        'diajukan' => ['Menunggu persetujuan', 'warn'],
        'disetujui' => ['Disetujui', 'info'],
        'dikirim_sebagian' => ['Dikirim sebagian', 'info'],
        'menunggu_backorder' => ['Menunggu backorder', 'bad'],
        'dikirim' => ['Dikirim', 'info'],
        'diterima' => ['Diterima', 'ok'],
        'selesai' => ['Selesai', 'ok'],
        'dibatalkan' => ['Dibatalkan', 'bad'],
    ];

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class)->with(['item.baseUnit', 'item.itemUnits', 'unit']);
    }

    public function deliveryNotes(): HasMany
    {
        return $this->hasMany(DeliveryNote::class)->latest('id');
    }

    public function statusLabel(): array
    {
        return self::STATUS[$this->status] ?? [$this->status, 'info'];
    }

    /** PO masih boleh diproses (kirim / backorder). */
    public function isOpenForShipping(): bool
    {
        return in_array($this->status, ['diajukan', 'dikirim_sebagian', 'menunggu_backorder'], true);
    }

    public function totalValue(): int
    {
        return (int) $this->items->sum(fn ($i) => round($i->qty_base * $i->unit_price_base));
    }

    public function hasBackorder(): bool
    {
        return $this->items->contains(fn ($i) => $i->remainingBase() > 0.0005);
    }
}
