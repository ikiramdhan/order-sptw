<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierPayment extends Model
{
    protected $fillable = ['supplier_purchase_id', 'amount', 'paid_at', 'method', 'notes', 'recorded_by'];

    protected function casts(): array
    {
        return ['paid_at' => 'date'];
    }
}
