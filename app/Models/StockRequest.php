<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockRequest extends Model
{
    public const REASONS = ['rusak' => 'Rusak', 'kadaluarsa' => 'Kadaluarsa', 'tumpah' => 'Tumpah / salah olah', 'lainnya' => 'Lainnya'];

    protected $fillable = ['type', 'outlet_id', 'item_id', 'qty_base', 'qty_system', 'unit_price', 'charged', 'charge_id', 'reason', 'notes', 'status', 'created_by', 'decided_by', 'decided_at', 'decision_note'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime', 'charged' => 'boolean'];
    }

    public function item(): BelongsTo { return $this->belongsTo(Item::class); }
    public function outlet(): BelongsTo { return $this->belongsTo(Outlet::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
