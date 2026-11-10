<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = ['invoice_id', 'amount', 'paid_at', 'method', 'notes', 'recorded_by'];

    public const METHODS = ['transfer' => 'Transfer', 'tunai' => 'Tunai', 'lainnya' => 'Lainnya'];

    protected function casts(): array
    {
        return ['paid_at' => 'date'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
