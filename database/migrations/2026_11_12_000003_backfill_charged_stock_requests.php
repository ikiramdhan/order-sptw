<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Pengajuan yang disetujui sebelum fitur tagihan ada: tandai yang seharusnya dibebankan ke outlet. */
    public function up(): void
    {
        DB::table('stock_requests')
            ->where('status', 'disetujui')->whereNotNull('outlet_id')->whereNull('charge_id')->where('charged', false)
            ->where(function ($q) {
                $q->where(function ($x) {
                    $x->where('type', 'so')->whereColumn('qty_base', '<', 'qty_system')->where(fn ($r) => $r->whereNull('reason')->orWhere('reason', '!=', 'awal'));
                })->orWhere(fn ($x) => $x->where('type', 'waste')->where('reason', 'tumpah'));
            })->update(['charged' => true]);
    }

    public function down(): void
    {
    }
};
