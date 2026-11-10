<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $fillable = ['name', 'phone', 'address', 'bank_name', 'account_name', 'account_number', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** "BCA 5875845184 a.n. UDIN ARDI" atau null bila belum diisi. */
    public function bankLine(): ?string
    {
        if (! $this->account_number && ! $this->bank_name) {
            return null;
        }

        return trim(($this->bank_name ?: '').' '.($this->account_number ?: '').($this->account_name ? ' a.n. '.$this->account_name : ''));
    }
}
