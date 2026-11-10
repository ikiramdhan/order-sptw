<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\DeliveryNote;
use App\Models\GoodsReceipt;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReceiptService
{
    /**
     * Outlet konfirmasi terima per surat jalan, lalu invoice terbit otomatis
     * berdasarkan qty yang benar-benar diterima.
     *
     * $received: [delivery_note_item_id => qty diterima (satuan surat jalan)]; item yang tidak diisi dianggap diterima penuh.
     * Mengembalikan invoice, atau null bila tidak ada barang yang diterima.
     */
    public static function confirm(DeliveryNote $note, array $received, ?string $notes, User $user): ?Invoice
    {
        return DB::transaction(function () use ($note, $received, $notes, $user) {
            $note = DeliveryNote::whereKey($note->id)->lockForUpdate()->firstOrFail();

            if ($note->status !== 'dikirim') {
                throw new \DomainException('Surat jalan ini sudah dikonfirmasi.');
            }

            $note->load(['items', 'purchaseOrder']);
            $invoiceLines = [];

            foreach ($note->items as $row) {
                $factor = $row->item->factorFor($row->unit_id);
                $input = array_key_exists($row->id, $received) && $received[$row->id] !== null && $received[$row->id] !== ''
                    ? round((float) $received[$row->id], 3)
                    : (float) $row->qty_input;

                if ($input < 0) {
                    throw new \DomainException("Qty diterima {$row->item->name} tidak boleh negatif.");
                }

                $base = round($input * $factor, 3);
                if (abs($base - (float) $row->qty_base) < 0.001) {
                    $base = (float) $row->qty_base;
                }
                if ($base > (float) $row->qty_base + 0.0005) {
                    throw new \DomainException("Qty diterima {$row->item->name} melebihi yang dikirim.");
                }

                $row->update(['qty_received_base' => $base]);

                if ($base > 0 && $row->purchase_order_item_id) {
                    $price = (int) $row->poItem->unit_price_base;
                    $invoiceLines[] = [
                        'item_id' => $row->item_id,
                        'unit_id' => $row->unit_id,
                        'qty_input' => round($base / $factor, 3),
                        'qty_base' => $base,
                        'unit_price_base' => $price,
                        'subtotal' => (int) round($base * $price),
                    ];
                }
            }

            GoodsReceipt::create([
                'delivery_note_id' => $note->id,
                'received_by' => $user->id,
                'received_at' => now(),
                'notes' => $notes,
            ]);

            $note->update(['status' => 'diterima']);

            if ($note->type === 'pengganti_retur' && $note->return_id) {
                \App\Models\ReturnRequest::whereKey($note->return_id)->update(['status' => 'selesai']);
            }

            $invoice = $invoiceLines === [] ? null : self::issueInvoice($note, $invoiceLines);

            if ($note->purchaseOrder) {
                OrderService::refreshStatus($note->purchaseOrder);
            }

            Notifier::roles(['admin_ck', 'gudang'], "{$note->number} diterima outlet", $note->outlet->name.($invoice ? " · invoice {$invoice->number}" : ''), '/surat-jalan/'.$note->id);
            ActivityLog::record('delivery_received', "{$note->number} diterima".($invoice ? ", invoice {$invoice->number}" : ''), $note, $user->id);

            return $invoice;
        });
    }

    private static function issueInvoice(DeliveryNote $note, array $lines): Invoice
    {
        $outlet = Outlet::whereKey($note->outlet_id)->lockForUpdate()->firstOrFail();
        $total = array_sum(array_column($lines, 'subtotal'));

        // Saldo kredit (dari Nota Retur) memotong invoice berikutnya.
        $credit = min((int) $outlet->credit_balance, $total);

        $invoice = Invoice::create([
            'number' => NumberGenerator::next('INV'),
            'outlet_id' => $outlet->id,
            'delivery_note_id' => $note->id,
            'issued_at' => today(),
            'due_at' => today()->addDays((int) $outlet->payment_term_days),
            'total' => $total,
            'credit_applied' => $credit,
            'paid' => 0,
            'status' => 'belum_dibayar',
        ]);

        foreach ($lines as $line) {
            InvoiceItem::create($line + ['invoice_id' => $invoice->id]);
        }

        if ($credit > 0) {
            $outlet->decrement('credit_balance', $credit);
        }

        $invoice->refreshStatus();

        return $invoice;
    }
}
