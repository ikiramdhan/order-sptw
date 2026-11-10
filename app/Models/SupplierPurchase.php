<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierPurchase extends Model
{
    protected $fillable = ['number', 'supplier_id', 'purchased_at', 'due_at', 'total', 'paid', 'payment_status', 'received_at', 'created_by', 'notes'];

    protected function casts(): array
    {
        return ['purchased_at' => 'date', 'due_at' => 'date', 'received_at' => 'datetime'];
    }

    public const STATUS = [
        'belum_dibayar' => ['Belum Dibayar', 'warn'],
        'sebagian' => ['Sebagian', 'info'],
        'lunas' => ['Lunas', 'ok'],
        'jatuh_tempo' => ['Jatuh Tempo', 'bad'],
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SupplierPurchaseItem::class)->with(['item', 'unit']);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public function remaining(): int
    {
        return max(0, $this->total - $this->paid);
    }

    public function refreshStatus(): void
    {
        $remaining = $this->remaining();

        if ($remaining <= 0) {
            $status = 'lunas';
        } elseif ($this->due_at->lt(today())) {
            $status = 'jatuh_tempo';
        } elseif ($this->paid > 0) {
            $status = 'sebagian';
        } else {
            $status = 'belum_dibayar';
        }

        $this->payment_status = $status;
        $this->save();
    }

    public function statusLabel(): array
    {
        return self::STATUS[$this->payment_status] ?? [$this->payment_status, 'info'];
    }
}
