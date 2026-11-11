<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryMessage extends Model
{
    protected $fillable = ['delivery_note_id', 'user_id', 'role', 'body'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
