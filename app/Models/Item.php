<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    protected $fillable = ['name', 'category', 'base_unit_id', 'price', 'stock', 'min_stock', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'price' => 'integer'];
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }

    public function itemUnits(): HasMany
    {
        return $this->hasMany(ItemUnit::class)->with('unit')->orderBy('factor');
    }

    public function recipe(): HasMany
    {
        return $this->hasMany(ItemRecipe::class)->with('ingredient.baseUnit');
    }

    /** Barang beresep: stoknya dihitung dari bahan, tidak disimpan sendiri. */
    public function hasRecipe(): bool
    {
        return $this->recipe->isNotEmpty();
    }

    /** Stok yang bisa dipenuhi (satuan dasar). Barang beresep = bahan yang paling cepat habis. */
    public function availableStock(): float
    {
        if (! $this->hasRecipe()) {
            return (float) $this->stock;
        }

        $min = null;
        foreach ($this->recipe as $r) {
            $per = (float) $r->qty_base;
            if ($per <= 0) {
                continue;
            }
            $can = (float) $r->ingredient->stock / $per;
            $min = $min === null ? $can : min($min, $can);
        }

        return floor(max(0, (float) $min) * 1000 + 1e-6) / 1000;
    }

    public function isLow(): bool
    {
        if ($this->hasRecipe()) {
            return false;
        }

        return (float) $this->stock <= (float) $this->min_stock && (float) $this->min_stock > 0;
    }

    /** Faktor konversi satuan ke satuan dasar (1 untuk satuan dasar). */
    public function factorFor(int $unitId): float
    {
        if ($unitId === (int) $this->base_unit_id) {
            return 1.0;
        }

        $row = $this->itemUnits->firstWhere('unit_id', $unitId);

        if (! $row) {
            throw new \InvalidArgumentException('Satuan tidak terdaftar untuk barang ini.');
        }

        return (float) $row->factor;
    }

    /** Satuan yang boleh dipilih outlet (selalu termasuk satuan dasar). */
    public function sellableUnits()
    {
        $units = collect([['unit_id' => $this->base_unit_id, 'name' => $this->baseUnit->name, 'factor' => 1.0]]);

        foreach ($this->itemUnits as $iu) {
            if ($iu->is_sellable && (int) $iu->unit_id !== (int) $this->base_unit_id) {
                $units->push(['unit_id' => $iu->unit_id, 'name' => $iu->unit->name, 'factor' => (float) $iu->factor]);
            }
        }

        return $units;
    }

    public function purchasableUnits()
    {
        $units = collect([['unit_id' => $this->base_unit_id, 'name' => $this->baseUnit->name, 'factor' => 1.0]]);

        foreach ($this->itemUnits as $iu) {
            if ($iu->is_purchasable && (int) $iu->unit_id !== (int) $this->base_unit_id) {
                $units->push(['unit_id' => $iu->unit_id, 'name' => $iu->unit->name, 'factor' => (float) $iu->factor]);
            }
        }

        return $units;
    }

    /** Harga per satuan tertentu = harga dasar x faktor. */
    public function priceFor(int $unitId): int
    {
        return (int) round($this->price * $this->factorFor($unitId));
    }
}
