<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\OutletCharge;
use App\Models\StockRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Tagihan minusan ke outlet: selisih opname kurang + waste karena kelalaian. */
class OutletChargeService
{
    /** Nilai rupiah satu pengajuan (positif). */
    public static function valueOf(StockRequest $r): int
    {
        $price = (int) ($r->unit_price ?? round((float) $r->item->price));
        $qty = $r->type === 'so' ? abs((float) $r->qty_base - (float) $r->qty_system) : (float) $r->qty_base;

        return (int) round($qty * $price);
    }

    /** Pengajuan yang dibebankan tapi belum masuk tagihan, sampai akhir bulan terpilih. */
    public static function pending(int $outletId, string $month)
    {
        $end = \Illuminate\Support\Carbon::parse($month.'-01')->endOfMonth();

        return StockRequest::with('item.baseUnit')->where('outlet_id', $outletId)->where('status', 'disetujui')
            ->where('charged', true)->whereNull('charge_id')->where('decided_at', '<=', $end)->orderBy('decided_at')->get();
    }

    public static function create(int $outletId, string $month, User $user): OutletCharge
    {
        return DB::transaction(function () use ($outletId, $month, $user) {
            $rows = StockRequest::whereIn('id', self::pending($outletId, $month)->pluck('id'))->lockForUpdate()->with('item')->get()->whereNull('charge_id');
            if ($rows->isEmpty()) {
                throw new \DomainException('Tidak ada minusan yang bisa ditagih untuk bulan ini.');
            }

            $outlet = \App\Models\Outlet::findOrFail($outletId);
            $total = (int) $rows->sum(fn ($r) => self::valueOf($r));
            $charge = OutletCharge::create([
                'number' => NumberGenerator::next('TM'), 'outlet_id' => $outletId, 'period' => $month, 'issued_at' => today(),
                'due_at' => today()->addDays((int) $outlet->payment_term_days), 'total' => $total, 'status' => 'belum_dibayar', 'created_by' => $user->id,
            ]);

            foreach ($rows as $r) {
                $charge->items()->create([
                    'stock_request_id' => $r->id, 'item_id' => $r->item_id, 'kind' => $r->type,
                    'description' => $r->type === 'so' ? 'Selisih opname kurang' : 'Waste: '.(StockRequest::REASONS[$r->reason] ?? $r->reason).($r->notes ? ' · '.$r->notes : ''),
                    'qty_base' => $r->type === 'so' ? abs((float) $r->qty_base - (float) $r->qty_system) : $r->qty_base,
                    'unit_price' => (int) ($r->unit_price ?? round((float) $r->item->price)), 'value' => self::valueOf($r),
                ]);
                $r->update(['charge_id' => $charge->id]);
            }

            Notifier::outlet($outletId, "Tagihan minusan {$charge->number}", 'Total '.\App\Support\Fmt::rp($total), '/tagihan-minusan/'.$charge->id);
            ActivityLog::record('charge_created', "{$charge->number} {$outlet->name}: ".\App\Support\Fmt::rp($total), $charge, $user->id);

            return $charge;
        });
    }

    public static function markPaid(OutletCharge $charge, User $user): void
    {
        if ($charge->status === 'lunas') {
            throw new \DomainException('Tagihan ini sudah lunas.');
        }
        $charge->update(['status' => 'lunas', 'paid_at' => today()]);
        ActivityLog::record('charge_paid', "{$charge->number} lunas", $charge, $user->id);
    }
}
