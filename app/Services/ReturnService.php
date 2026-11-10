<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\CreditNote;
use App\Models\DeliveryNote;
use App\Models\DeliveryNoteItem;
use App\Models\Invoice;
use App\Models\Outlet;
use App\Models\ReturnItem;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReturnService
{
    /**
     * Barang yang masih boleh diretur dari sebuah surat jalan (qty diterima dikurangi retur sebelumnya).
     * @return \Illuminate\Support\Collection  item_id => ['item','unit','unit_id','factor','received_base','available_base','price_base']
     */
    public static function eligible(DeliveryNote $note)
    {
        $note->loadMissing(['items.item.baseUnit', 'items.unit', 'invoice.items']);

        if ($note->type !== 'regular' || $note->status !== 'diterima' || ! $note->invoice) {
            return collect();
        }

        $returned = ReturnItem::query()
            ->whereHas('returnRequest', fn ($q) => $q->where('delivery_note_id', $note->id)->whereIn('status', ['diajukan', 'disetujui', 'selesai']))
            ->get()->groupBy('item_id')->map(fn ($rows) => (float) $rows->sum('qty_base'));

        $out = collect();

        foreach ($note->items->groupBy('item_id') as $itemId => $rows) {
            $first = $rows->first();
            $received = round((float) $rows->sum(fn ($r) => (float) $r->qty_received_base), 3);
            $available = round($received - (float) ($returned[$itemId] ?? 0), 3);
            $price = (int) optional($note->invoice->items->firstWhere('item_id', $itemId))->unit_price_base;

            if ($received <= 0) {
                continue;
            }

            $out[$itemId] = [
                'item' => $first->item, 'unit' => $first->unit, 'unit_id' => $first->unit_id,
                'factor' => $first->item->factorFor($first->unit_id),
                'received_base' => $received, 'available_base' => max(0, $available), 'price_base' => $price,
            ];
        }

        return $out;
    }

    /** $lines: [item_id => ['qty' => qty dalam satuan surat jalan, 'reason' => rusak|tidak_sesuai|kedaluwarsa]] */
    public static function create(User $user, DeliveryNote $note, array $lines, ?string $notes): ReturnRequest
    {
        return DB::transaction(function () use ($user, $note, $lines, $notes) {
            $eligible = self::eligible($note);
            $rows = [];

            foreach ($lines as $itemId => $line) {
                $qty = round((float) ($line['qty'] ?? 0), 3);
                if ($qty <= 0) {
                    continue;
                }

                $e = $eligible->get($itemId);
                if (! $e) {
                    throw new \DomainException('Barang tidak termasuk dalam surat jalan ini atau tidak bisa diretur.');
                }
                if (! array_key_exists($line['reason'] ?? '', ReturnRequest::REASONS)) {
                    throw new \DomainException("Pilih alasan retur untuk {$e['item']->name}.");
                }

                $base = round($qty * $e['factor'], 3);
                if (abs($base - $e['available_base']) < 0.001) {
                    $base = $e['available_base'];
                }
                if ($base > $e['available_base'] + 0.0005) {
                    throw new \DomainException("Qty retur {$e['item']->name} melebihi yang bisa diretur (".\App\Support\Fmt::qty($e['available_base'] / $e['factor']).' '.$e['unit']->name.').');
                }

                $rows[] = [
                    'item_id' => $itemId, 'unit_id' => $e['unit_id'], 'qty_input' => round($base / $e['factor'], 3),
                    'qty_base' => $base, 'reason' => $line['reason'], 'value' => (int) round($base * $e['price_base']),
                ];
            }

            if ($rows === []) {
                throw new \DomainException('Isi jumlah barang yang diretur.');
            }

            $ret = ReturnRequest::create([
                'number' => NumberGenerator::next('RT'),
                'outlet_id' => $note->outlet_id,
                'delivery_note_id' => $note->id,
                'invoice_id' => $note->invoice->id,
                'requested_by' => $user->id,
                'status' => 'diajukan',
                'notes' => $notes,
            ]);

            foreach ($rows as $r) {
                ReturnItem::create($r + ['return_id' => $ret->id]);
            }

            ActivityLog::record('return_submitted', "{$ret->number} diajukan", $ret, $user->id);
            Notifier::roles(['admin_ck'], "Retur baru {$ret->number}", $note->outlet->name.' mengajukan retur', '/retur/'.$ret->id);

            return $ret;
        });
    }

    /**
     * Admin CK memutuskan retur.
     * $resolution: potong_tagihan | barang_pengganti
     * $dispositions: [return_item_id => masuk_stok | dibuang]
     */
    public static function approve(ReturnRequest $ret, string $resolution, array $dispositions, User $user): void
    {
        if (! array_key_exists($resolution, ReturnRequest::RESOLUTIONS)) {
            throw new \DomainException('Pilih penyelesaian retur.');
        }

        DB::transaction(function () use ($ret, $resolution, $dispositions, $user) {
            $ret = ReturnRequest::whereKey($ret->id)->lockForUpdate()->firstOrFail();

            if ($ret->status !== 'diajukan') {
                throw new \DomainException('Retur ini sudah diputuskan.');
            }

            $ret->load(['items', 'outlet']);
            $total = 0;

            foreach ($ret->items as $row) {
                $disp = $dispositions[$row->id] ?? 'dibuang';
                if (! array_key_exists($disp, ReturnItem::DISPOSITIONS)) {
                    throw new \DomainException('Pilihan stok tidak valid.');
                }

                $row->update(['disposition' => $disp]);
                $total += (int) $row->value;

                if ($disp === 'masuk_stok') {
                    StockService::move($row->item, 'retur_masuk', (float) $row->qty_base, $ret, $user->id, $ret->number);
                }
            }

            if ($resolution === 'potong_tagihan') {
                self::issueCreditNote($ret, $total);
                $status = 'selesai';
            } else {
                self::issueReplacement($ret, $user);
                $status = 'disetujui';
            }

            $ret->update(['status' => $status, 'resolution' => $resolution, 'decided_by' => $user->id, 'decided_at' => now()]);

            ActivityLog::record('return_approved', "{$ret->number}: ".ReturnRequest::RESOLUTIONS[$resolution], $ret, $user->id);
            Notifier::outlet($ret->outlet_id, "Retur {$ret->number} disetujui", ReturnRequest::RESOLUTIONS[$resolution], '/retur/'.$ret->id);
        });
    }

    public static function reject(ReturnRequest $ret, string $reason, User $user): void
    {
        DB::transaction(function () use ($ret, $reason, $user) {
            $ret = ReturnRequest::whereKey($ret->id)->lockForUpdate()->firstOrFail();

            if ($ret->status !== 'diajukan') {
                throw new \DomainException('Retur ini sudah diputuskan.');
            }

            $ret->update(['status' => 'ditolak', 'reject_reason' => $reason, 'decided_by' => $user->id, 'decided_at' => now()]);
            ActivityLog::record('return_rejected', "{$ret->number}: {$reason}", $ret, $user->id);
            Notifier::outlet($ret->outlet_id, "Retur {$ret->number} ditolak", $reason, '/retur/'.$ret->id);
        });
    }

    /** Nilai retur memotong invoice asalnya bila masih ada sisa; kelebihannya jadi saldo kredit outlet. */
    private static function issueCreditNote(ReturnRequest $ret, int $amount): void
    {
        $invoice = $ret->invoice_id ? Invoice::whereKey($ret->invoice_id)->lockForUpdate()->first() : null;
        $outlet = Outlet::whereKey($ret->outlet_id)->lockForUpdate()->firstOrFail();

        $apply = 0;
        if ($invoice && $invoice->status !== 'dibatalkan') {
            $apply = min($amount, $invoice->remaining());
            if ($apply > 0) {
                $invoice->credit_applied += $apply;
                $invoice->save();
                $invoice->refreshStatus();
            }
        }

        $toBalance = $amount - $apply;
        if ($toBalance > 0) {
            $outlet->increment('credit_balance', $toBalance);
        }

        CreditNote::create([
            'number' => NumberGenerator::next('NR'),
            'return_id' => $ret->id,
            'outlet_id' => $outlet->id,
            'invoice_id' => $invoice?->id,
            'amount' => $amount,
            'applied_to_invoice' => $apply,
            'to_balance' => $toBalance,
            'issued_at' => today(),
        ]);
    }

    /** Surat jalan pengganti: stok keluar, tanpa invoice (outlet tetap konfirmasi terima). */
    private static function issueReplacement(ReturnRequest $ret, User $user): void
    {
        $orig = DeliveryNote::findOrFail($ret->delivery_note_id);

        $note = DeliveryNote::create([
            'number' => NumberGenerator::next('SJ'),
            'purchase_order_id' => $orig->purchase_order_id,
            'outlet_id' => $ret->outlet_id,
            'type' => 'pengganti_retur',
            'return_id' => $ret->id,
            'status' => 'dikirim',
            'created_by' => $user->id,
            'shipped_at' => now(),
        ]);

        foreach ($ret->items as $row) {
            StockService::move($row->item, 'retur_pengganti', -(float) $row->qty_base, $note, $user->id, $note->number);

            DeliveryNoteItem::create([
                'delivery_note_id' => $note->id,
                'purchase_order_item_id' => null,
                'item_id' => $row->item_id,
                'unit_id' => $row->unit_id,
                'qty_input' => $row->qty_input,
                'qty_base' => $row->qty_base,
            ]);
        }
    }
}
