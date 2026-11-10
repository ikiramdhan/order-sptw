<?php

namespace App\Services;

use App\Models\Item;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class StockService
{
    /**
     * Ubah stok (satuan dasar). qtyBase bertanda: + masuk, - keluar.
     * Menolak stok negatif kecuali $allowNegative.
     */
    public static function move(Item $item, string $type, float $qtyBase, ?Model $ref = null, ?int $userId = null, ?string $notes = null, bool $allowNegative = false): StockMovement
    {
        return DB::transaction(function () use ($item, $type, $qtyBase, $ref, $userId, $notes, $allowNegative) {
            $locked = Item::whereKey($item->id)->lockForUpdate()->firstOrFail();
            $new = round((float) $locked->stock + $qtyBase, 3);

            if ($new < -0.0005 && ! $allowNegative) {
                throw new \DomainException("Stok {$locked->name} tidak cukup (tersedia ".\App\Support\Fmt::qty($locked->stock).').');
            }

            $locked->update(['stock' => $new]);
            $item->stock = $new;

            return StockMovement::create([
                'item_id' => $item->id,
                'type' => $type,
                'qty_base' => round($qtyBase, 3),
                'balance_after' => $new,
                'ref_type' => $ref ? class_basename($ref) : null,
                'ref_id' => $ref?->getKey(),
                'user_id' => $userId,
                'notes' => $notes,
            ]);
        });
    }

    /** Stok opname: samakan stok sistem dengan hitungan fisik (satuan dasar). */
    public static function adjust(Item $item, float $physicalBase, string $reason, ?string $notes, int $userId): ?StockAdjustment
    {
        return DB::transaction(function () use ($item, $physicalBase, $reason, $notes, $userId) {
            $locked = Item::whereKey($item->id)->lockForUpdate()->firstOrFail();
            $system = (float) $locked->stock;
            $diff = round($physicalBase - $system, 3);

            if (abs($diff) < 0.0005) {
                return null;
            }

            $adj = StockAdjustment::create([
                'item_id' => $item->id,
                'qty_system' => $system,
                'qty_physical' => $physicalBase,
                'difference' => $diff,
                'reason' => $reason,
                'notes' => $notes,
                'user_id' => $userId,
            ]);

            self::move($locked, 'penyesuaian', $diff, $adj, $userId, StockAdjustment::REASONS[$reason] ?? $reason, true);

            return $adj;
        });
    }
}
