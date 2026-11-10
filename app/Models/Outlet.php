<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Outlet extends Model
{
    public const TERMS = [
        0 => 'Tunai',
        7 => 'Tempo 7 hari',
        14 => 'Tempo 14 hari',
        30 => 'Tempo 30 hari',
    ];

    protected $fillable = ['name', 'address', 'pic_name', 'phone', 'payment_term_days', 'credit_balance', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function termLabel(): string
    {
        return self::TERMS[$this->payment_term_days] ?? 'Tempo '.$this->payment_term_days.' hari';
    }

    public function initials(): string
    {
        $name = preg_replace('/^outlet\s+/i', '', $this->name);

        return mb_strtoupper(mb_substr($name, 0, 2));
    }
}
