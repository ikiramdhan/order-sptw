<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ReturnRequest extends Model
{
    protected $table = 'returns';

    protected $fillable = [
        'number', 'outlet_id', 'delivery_note_id', 'invoice_id', 'requested_by', 'status', 'resolution',
        'notes', 'reject_reason', 'decided_by', 'decided_at',
    ];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    public const STATUS = [
        'diajukan' => ['Menunggu keputusan', 'warn'],
        'disetujui' => ['Disetujui, menunggu barang pengganti', 'info'],
        'ditolak' => ['Ditolak', 'bad'],
        'selesai' => ['Selesai', 'ok'],
    ];

    public const RESOLUTIONS = [
        'potong_tagihan' => 'Potong tagihan (Nota Retur)',
        'barang_pengganti' => 'Kirim barang pengganti',
    ];

    public const REASONS = ['rusak' => 'Rusak', 'tidak_sesuai' => 'Tidak sesuai pesanan', 'kedaluwarsa' => 'Kedaluwarsa'];

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function deliveryNote(): BelongsTo
    {
        return $this->belongsTo(DeliveryNote::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReturnItem::class, 'return_id')->with(['item.baseUnit', 'unit']);
    }

    public function creditNote(): HasOne
    {
        return $this->hasOne(CreditNote::class, 'return_id');
    }

    public function replacement(): HasOne
    {
        return $this->hasOne(DeliveryNote::class, 'return_id');
    }

    public function statusLabel(): array
    {
        return self::STATUS[$this->status] ?? [$this->status, 'info'];
    }

    public function totalValue(): int
    {
        return (int) $this->items->sum('value');
    }
}
