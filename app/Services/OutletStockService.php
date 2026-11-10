<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Item;
use App\Models\StockRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** Stok di outlet: masuk dari surat jalan, terjual dari laporan ESB, opname, waste, retur. */
class OutletStockService
{
    public const LABELS = ['masuk' => 'Barang masuk', 'terjual' => 'Terjual (ESB)', 'so' => 'Stok opname', 'waste' => 'Waste', 'retur' => 'Retur ke CK'];

    /** Ubah stok outlet (satuan dasar). Boleh negatif: data ESB bisa mendahului penerimaan barang. */
    public static function move(int $outletId, Item|int $item, string $type, float $qtyBase, Model|array|null $ref = null, ?int $userId = null, ?string $notes = null, ?string $date = null): void
    {
        $itemId = $item instanceof Item ? $item->id : $item;

        DB::transaction(function () use ($outletId, $itemId, $type, $qtyBase, $ref, $userId, $notes, $date) {
            DB::table('outlet_stocks')->insertOrIgnore(['outlet_id' => $outletId, 'item_id' => $itemId, 'qty' => 0, 'created_at' => now(), 'updated_at' => now()]);
            $row = DB::table('outlet_stocks')->where('outlet_id', $outletId)->where('item_id', $itemId)->lockForUpdate()->first();
            $new = round((float) $row->qty + $qtyBase, 3);
            DB::table('outlet_stocks')->where('id', $row->id)->update(['qty' => $new, 'updated_at' => now()]);
            DB::table('outlet_stock_movements')->insert([
                'outlet_id' => $outletId, 'item_id' => $itemId, 'type' => $type, 'qty_base' => round($qtyBase, 3), 'balance_after' => $new,
                'occurred_on' => $date ?? now()->toDateString(),
                'ref_type' => is_array($ref) ? $ref[0] : ($ref ? class_basename($ref) : null), 'ref_id' => is_array($ref) ? $ref[1] : $ref?->getKey(),
                'user_id' => $userId, 'notes' => $notes, 'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    public static function balance(int $outletId, int $itemId): float
    {
        return (float) (DB::table('outlet_stocks')->where('outlet_id', $outletId)->where('item_id', $itemId)->value('qty') ?? 0);
    }

    /** Waste. outletId null = gudang CK. Dari outlet: menunggu persetujuan Admin CK; dari Admin/Gudang: langsung berlaku. */
    public static function submitWaste(User $user, ?int $outletId, int $itemId, float $qty, string $reason, ?string $notes): StockRequest
    {
        if ($qty <= 0) {
            throw new \DomainException('Jumlah waste harus lebih dari 0.');
        }
        if (! array_key_exists($reason, StockRequest::REASONS)) {
            throw new \DomainException('Pilih alasan waste.');
        }
        $item = Item::with('recipe')->findOrFail($itemId);

        return DB::transaction(function () use ($user, $outletId, $item, $qty, $reason, $notes) {
            $auto = $user->role !== 'outlet';
            $req = StockRequest::create([
                'type' => 'waste', 'outlet_id' => $outletId, 'item_id' => $item->id, 'qty_base' => round($qty, 3),
                'reason' => $reason, 'notes' => $notes, 'status' => 'menunggu', 'created_by' => $user->id,
            ]);

            if ($auto) {
                self::apply($req, $user);
            } else {
                Notifier::roles(['admin_ck'], 'Waste outlet menunggu persetujuan', $req->outlet->name.' · '.$item->name, '/persetujuan-stok');
            }

            return $req;
        });
    }

    /** Stok opname outlet: hasil hitung fisik per barang. */
    public static function submitCount(User $user, int $outletId, int $itemId, float $physical, ?string $notes): ?StockRequest
    {
        if ($physical < 0) {
            throw new \DomainException('Stok fisik tidak boleh negatif.');
        }

        return DB::transaction(function () use ($user, $outletId, $itemId, $physical, $notes) {
            $system = self::balance($outletId, $itemId);
            if (abs($physical - $system) < 0.0005) {
                return null;
            }
            $req = StockRequest::create([
                'type' => 'so', 'outlet_id' => $outletId, 'item_id' => $itemId, 'qty_base' => round($physical, 3), 'qty_system' => $system,
                'notes' => $notes, 'status' => 'menunggu', 'created_by' => $user->id,
            ]);
            if ($user->role !== 'outlet') {
                self::apply($req, $user);
            }

            return $req;
        });
    }

    public static function approve(StockRequest $req, User $user): void
    {
        DB::transaction(function () use ($req, $user) {
            $req = StockRequest::whereKey($req->id)->lockForUpdate()->firstOrFail();
            if ($req->status !== 'menunggu') {
                throw new \DomainException('Pengajuan ini sudah diputuskan.');
            }
            self::apply($req, $user);
            if ($req->outlet_id) {
                Notifier::outlet($req->outlet_id, ($req->type === 'waste' ? 'Waste' : 'Stok opname').' disetujui', $req->item->name, '/stok-outlet');
            }
        });
    }

    public static function reject(StockRequest $req, string $note, User $user): void
    {
        DB::transaction(function () use ($req, $note, $user) {
            $req = StockRequest::whereKey($req->id)->lockForUpdate()->firstOrFail();
            if ($req->status !== 'menunggu') {
                throw new \DomainException('Pengajuan ini sudah diputuskan.');
            }
            $req->update(['status' => 'ditolak', 'decided_by' => $user->id, 'decided_at' => now(), 'decision_note' => $note]);
            Notifier::outlet($req->outlet_id, ($req->type === 'waste' ? 'Waste' : 'Stok opname').' ditolak', $note, '/stok-outlet');
            ActivityLog::record('stock_request_rejected', "{$req->type} {$req->item->name}: {$note}", $req, $user->id);
        });
    }

    /** Terapkan pengajuan ke stok (CK atau outlet) dan tandai disetujui. */
    private static function apply(StockRequest $req, User $user): void
    {
        $item = $req->item;
        $label = $req->type === 'waste' ? 'Waste: '.(StockRequest::REASONS[$req->reason] ?? $req->reason).($req->notes ? ' · '.$req->notes : '') : 'Stok opname'.($req->notes ? ': '.$req->notes : '');

        if ($req->outlet_id === null) {
            // Waste gudang CK
            StockService::move($item, 'waste', -(float) $req->qty_base, $req, $user->id, $label);
        } elseif ($req->type === 'waste') {
            self::move($req->outlet_id, $item, 'waste', -(float) $req->qty_base, $req, $user->id, $label);
        } else {
            $diff = round((float) $req->qty_base - self::balance($req->outlet_id, $item->id), 3);
            $req->qty_system = self::balance($req->outlet_id, $item->id);
            if (abs($diff) >= 0.0005) {
                self::move($req->outlet_id, $item, 'so', $diff, $req, $user->id, $label);
            }
        }

        $req->update(['status' => 'disetujui', 'decided_by' => $user->id, 'decided_at' => now(), 'qty_system' => $req->qty_system]);
        ActivityLog::record('stock_request_approved', "{$req->type} {$item->name} ".\App\Support\Fmt::qty($req->qty_base), $req, $user->id);
    }

    /**
     * Terapkan laporan penjualan ESB. $lines: [['item' => Item, 'qty' => float]].
     * Bila sudah ada impor untuk outlet+tanggal yang sama, harus $replace = true (impor lama dibatalkan dulu).
     */
    public static function applySales(int $outletId, string $date, array $lines, int $skipped, ?string $filename, User $user, bool $replace): int
    {
        return DB::transaction(function () use ($outletId, $date, $lines, $skipped, $filename, $user, $replace) {
            $old = DB::table('outlet_sales_imports')->where('outlet_id', $outletId)->where('sales_date', $date)->where('is_voided', false)->get();
            if ($old->isNotEmpty() && ! $replace) {
                throw new \DomainException('Penjualan tanggal itu sudah pernah diimpor. Centang "Ganti data tanggal itu" untuk menimpa.');
            }
            foreach ($old as $o) {
                $moves = DB::table('outlet_stock_movements')->where('ref_type', 'OutletSalesImport')->where('ref_id', $o->id)->get();
                foreach ($moves as $m) {
                    self::move($outletId, $m->item_id, 'terjual', -(float) $m->qty_base, null, $user->id, 'Pembatalan impor lama', $date);
                }
                DB::table('outlet_sales_imports')->where('id', $o->id)->update(['is_voided' => true, 'updated_at' => now()]);
            }

            $importId = DB::table('outlet_sales_imports')->insertGetId([
                'outlet_id' => $outletId, 'sales_date' => $date, 'filename' => $filename, 'rows_ok' => count($lines), 'rows_skipped' => $skipped,
                'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now(),
            ]);

            foreach ($lines as $l) {
                self::move($outletId, $l['item'], 'terjual', -round($l['qty'], 3), ['OutletSalesImport', $importId], $user->id, 'Laporan ESB', $date);
            }
            ActivityLog::record('sales_imported', "Impor penjualan ESB {$date}: ".count($lines).' barang');

            return $importId;
        });
    }
}
