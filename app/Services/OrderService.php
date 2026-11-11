<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\DeliveryNote;
use App\Models\DeliveryNoteItem;
use App\Models\Item;
use App\Models\Outlet;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrderService
{
    /**
     * Outlet mengirim PO dari keranjang.
     * $lines: [['item_id' => 1, 'unit_id' => 2, 'qty' => 2.0], ...]
     */
    public static function createFromCart(User $user, Outlet $outlet, array $lines, ?string $notes = null): PurchaseOrder
    {
        if (! $outlet->is_active) {
            throw new \DomainException('Outlet nonaktif tidak bisa membuat PO.');
        }

        if (count($lines) === 0) {
            throw new \DomainException('Keranjang masih kosong.');
        }

        return DB::transaction(function () use ($user, $outlet, $lines, $notes) {
            $po = PurchaseOrder::create([
                'number' => NumberGenerator::next('PO'),
                'outlet_id' => $outlet->id,
                'created_by' => $user->id,
                'status' => 'diajukan',
                'notes' => $notes,
                'submitted_at' => now(),
            ]);

            foreach ($lines as $line) {
                $item = Item::with(['baseUnit', 'itemUnits'])->findOrFail($line['item_id']);
                $unitId = (int) $line['unit_id'];
                $qty = round((float) $line['qty'], 3);

                if (! $item->is_active) {
                    throw new \DomainException("{$item->name} sedang tidak dijual.");
                }
                if ($qty <= 0) {
                    throw new \DomainException("Qty {$item->name} harus lebih dari 0.");
                }
                if (! $item->sellableUnits()->contains('unit_id', $unitId)) {
                    throw new \DomainException("Satuan untuk {$item->name} tidak diizinkan.");
                }

                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'item_id' => $item->id,
                    'unit_id' => $unitId,
                    'qty_input' => $qty,
                    'qty_base' => round($qty * $item->factorFor($unitId), 3),
                    'unit_price_base' => $item->price,
                ]);
            }

            Notifier::roles(['admin_ck', 'gudang'], "PO baru {$po->number}", "{$outlet->name} mengajukan pesanan", '/po/'.$po->id);
            ActivityLog::record('po_submitted', "{$outlet->name} mengajukan {$po->number}", $po, $user->id);

            return $po->load('items');
        });
    }

    /**
     * Admin menyetujui PO dan mengirim sebagian/seluruhnya.
     * $ship: [purchase_order_item_id => qty dalam satuan PO]
     * Mengembalikan surat jalan baru, atau null bila tidak ada barang yang dikirim (semua jadi backorder).
     */
    public static function approveAndShip(PurchaseOrder $po, array $ship, User $user): ?DeliveryNote
    {
        return DB::transaction(function () use ($po, $ship, $user) {
            $po = PurchaseOrder::whereKey($po->id)->lockForUpdate()->firstOrFail();

            if (! $po->isOpenForShipping()) {
                throw new \DomainException('PO ini tidak bisa diproses lagi.');
            }

            $po->load('items');
            $lines = [];

            foreach ($po->items as $poItem) {
                $inputQty = round((float) ($ship[$poItem->id] ?? 0), 3);
                if ($inputQty <= 0) {
                    continue;
                }

                $baseQty = round($inputQty * $poItem->factor(), 3);
                $remaining = $poItem->remainingBase();

                if (abs($baseQty - $remaining) < 0.001) {
                    $baseQty = $remaining; // hindari sisa pecahan
                }
                if ($baseQty > $remaining + 0.0005) {
                    throw new \DomainException("Qty kirim {$poItem->item->name} melebihi sisa PO.");
                }

                $lines[] = [$poItem, $baseQty];
            }

            if (! $po->approved_at) {
                $po->approved_at = now();
                $po->approved_by = $user->id;
            }

            $note = null;

            if ($lines !== []) {
                $note = DeliveryNote::create([
                    'number' => NumberGenerator::next('SJ'),
                    'purchase_order_id' => $po->id,
                    'outlet_id' => $po->outlet_id,
                    'type' => 'regular',
                    'status' => 'dikirim',
                    'created_by' => $user->id,
                    'shipped_at' => now(),
                ]);

                foreach ($lines as [$poItem, $baseQty]) {
                    StockService::moveSale($poItem->item, 'penjualan', -$baseQty, $note, $user->id, $note->number);

                    DeliveryNoteItem::create([
                        'delivery_note_id' => $note->id,
                        'purchase_order_item_id' => $poItem->id,
                        'item_id' => $poItem->item_id,
                        'unit_id' => $poItem->unit_id,
                        'qty_input' => round($baseQty / $poItem->factor(), 3),
                        'qty_base' => $baseQty,
                    ]);

                    $poItem->update(['qty_shipped_base' => round($poItem->qty_shipped_base + $baseQty, 3)]);
                }
            }

            $po->save();
            self::refreshStatus($po);
            if ($note) {
                Notifier::outlet($po->outlet_id, "Barang dikirim: {$note->number}", "Untuk {$po->number}", '/surat-jalan/'.$note->id);
            }
            ActivityLog::record('po_shipped', $note ? "{$po->number}: surat jalan {$note->number}" : "{$po->number}: disetujui, menunggu backorder", $po, $user->id);

            return $note;
        });
    }

    /** Batalkan sisa backorder (sisa PO yang belum dikirim). */
    public static function cancelBackorder(PurchaseOrder $po, string $reason, User $user): void
    {
        DB::transaction(function () use ($po, $reason, $user) {
            $po = PurchaseOrder::whereKey($po->id)->lockForUpdate()->firstOrFail();
            $po->load('items');

            foreach ($po->items as $poItem) {
                $remaining = $poItem->remainingBase();
                if ($remaining > 0.0005) {
                    $poItem->update(['qty_cancelled_base' => round($poItem->qty_cancelled_base + $remaining, 3)]);
                }
            }

            $po->cancel_reason = $reason;
            $po->save();
            self::refreshStatus($po->refresh());
            ActivityLog::record('po_backorder_cancelled', "{$po->number}: {$reason}", $po, $user->id);
        });
    }

    /** Batalkan PO yang belum pernah dikirim. */
    public static function cancel(PurchaseOrder $po, string $reason, User $user): void
    {
        if ($po->deliveryNotes()->exists() || ! in_array($po->status, ['diajukan', 'menunggu_backorder'], true)) {
            throw new \DomainException('PO yang sudah ada pengirimannya tidak bisa dibatalkan.');
        }

        $po->update(['status' => 'dibatalkan', 'cancel_reason' => $reason]);
        ActivityLog::record('po_cancelled', "{$po->number}: {$reason}", $po, $user->id);
    }

    /** Hitung ulang status PO dari surat jalan dan sisa backorder. */
    public static function refreshStatus(PurchaseOrder $po): void
    {
        $po->load('items');
        $hasNotes = $po->deliveryNotes()->exists();
        $openNotes = $po->deliveryNotes()->where('status', 'dikirim')->exists();
        $backorder = $po->hasBackorder();

        if (! $hasNotes) {
            $status = $backorder ? ($po->approved_at ? 'menunggu_backorder' : 'diajukan') : 'dibatalkan';
        } elseif ($openNotes) {
            $status = $backorder ? 'dikirim_sebagian' : 'dikirim';
        } else {
            $status = $backorder ? 'menunggu_backorder' : 'selesai';
        }

        $po->status = $status;
        $po->save();
    }
}
