<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Item;
use App\Models\Supplier;
use App\Models\SupplierPurchase;
use App\Models\SupplierPurchaseItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PurchaseService
{
    /**
     * $lines: [['item_id', 'unit_id', 'qty', 'unit_price' (per satuan input, rupiah)], ...]
     */
    public static function create(User $user, Supplier $supplier, string $purchasedAt, string $dueAt, array $lines, ?string $notes, bool $receiveNow): SupplierPurchase
    {
        if ($lines === []) {
            throw new \DomainException('Tambahkan minimal satu barang.');
        }

        return DB::transaction(function () use ($user, $supplier, $purchasedAt, $dueAt, $lines, $notes, $receiveNow) {
            $purchase = SupplierPurchase::create([
                'number' => NumberGenerator::next('PB'),
                'supplier_id' => $supplier->id,
                'purchased_at' => $purchasedAt,
                'due_at' => $dueAt,
                'total' => 0,
                'paid' => 0,
                'payment_status' => 'belum_dibayar',
                'created_by' => $user->id,
                'notes' => $notes,
            ]);

            $total = 0;

            foreach ($lines as $line) {
                $item = Item::with(['baseUnit', 'itemUnits'])->findOrFail($line['item_id']);
                $unitId = (int) $line['unit_id'];
                $qty = round((float) $line['qty'], 3);
                $price = (int) $line['unit_price'];

                if ($qty <= 0) {
                    throw new \DomainException("Qty {$item->name} harus lebih dari 0.");
                }
                if (! $item->purchasableUnits()->contains('unit_id', $unitId)) {
                    throw new \DomainException("Satuan beli untuk {$item->name} tidak diizinkan.");
                }

                $subtotal = (int) round($qty * $price);
                $total += $subtotal;

                SupplierPurchaseItem::create([
                    'supplier_purchase_id' => $purchase->id,
                    'item_id' => $item->id,
                    'unit_id' => $unitId,
                    'qty_input' => $qty,
                    'qty_base' => round($qty * $item->factorFor($unitId), 3),
                    'unit_price' => $price,
                    'subtotal' => $subtotal,
                ]);
            }

            $purchase->total = $total;
            $purchase->save();
            $purchase->refreshStatus();

            ActivityLog::record('purchase_created', "{$purchase->number} dari {$supplier->name}", $purchase, $user->id);

            if ($receiveNow) {
                self::receive($purchase, $user);
            }

            return $purchase;
        });
    }

    /** Barang diterima: stok bertambah (sekali saja). */
    public static function receive(SupplierPurchase $purchase, User $user): void
    {
        DB::transaction(function () use ($purchase, $user) {
            $purchase = SupplierPurchase::whereKey($purchase->id)->lockForUpdate()->firstOrFail();

            if ($purchase->received_at) {
                throw new \DomainException('Pembelian ini sudah diterima.');
            }

            foreach ($purchase->items as $row) {
                StockService::move($row->item, 'pembelian', (float) $row->qty_base, $purchase, $user->id, $purchase->number);
            }

            $purchase->update(['received_at' => now()]);
            ActivityLog::record('purchase_received', "{$purchase->number} diterima, stok bertambah", $purchase, $user->id);
        });
    }
}
