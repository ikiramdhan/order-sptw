<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $fillable = [
        'number', 'outlet_id', 'delivery_note_id', 'issued_at', 'due_at', 'total', 'credit_applied', 'paid', 'status', 'cancel_reason',
    ];

    protected function casts(): array
    {
        return ['issued_at' => 'date', 'due_at' => 'date'];
    }

    /** status => [label, warna badge] */
    public const STATUS = [
        'belum_dibayar' => ['Belum Dibayar', 'warn'],
        'sebagian' => ['Sebagian', 'info'],
        'lunas' => ['Lunas', 'ok'],
        'jatuh_tempo' => ['Jatuh Tempo', 'bad'],
        'dibatalkan' => ['Dibatalkan', 'bad'],
    ];

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function deliveryNote(): BelongsTo
    {
        return $this->belongsTo(DeliveryNote::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->with(['item.baseUnit', 'unit']);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest('paid_at')->latest('id');
    }

    /** Sisa tagihan = total - potongan (saldo kredit/retur) - pembayaran. */
    public function remaining(): int
    {
        return max(0, $this->total - $this->credit_applied - $this->paid);
    }

    public function refreshStatus(): void
    {
        if ($this->status === 'dibatalkan') {
            return;
        }

        if ($this->remaining() <= 0) {
            $status = 'lunas';
        } elseif ($this->due_at->lt(today())) {
            $status = 'jatuh_tempo';
        } elseif ($this->paid + $this->credit_applied > 0) {
            $status = 'sebagian';
        } else {
            $status = 'belum_dibayar';
        }

        $this->status = $status;
        $this->save();
    }

    public function statusLabel(): array
    {
        return self::STATUS[$this->status] ?? [$this->status, 'info'];
    }
}
