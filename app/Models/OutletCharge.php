<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OutletCharge extends Model
{
    protected $fillable = ['number', 'outlet_id', 'period', 'issued_at', 'due_at', 'total', 'status', 'paid_at', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['issued_at' => 'date', 'due_at' => 'date', 'paid_at' => 'date'];
    }

    public function outlet(): BelongsTo { return $this->belongsTo(Outlet::class); }
    public function items(): HasMany { return $this->hasMany(OutletChargeItem::class); }
}
