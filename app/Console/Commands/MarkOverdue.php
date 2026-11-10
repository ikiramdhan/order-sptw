<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Dijalankan harian oleh cron (lihat routes/console.php): ubah status jatuh tempo. */
class MarkOverdue extends Command
{
    protected $signature = 'ck:mark-overdue';
    protected $description = 'Tandai invoice outlet dan tagihan supplier yang lewat jatuh tempo';

    public function handle(): int
    {
        $today = now()->toDateString();

        $invoices = DB::table('invoices')
            ->whereIn('status', ['belum_dibayar', 'sebagian'])
            ->whereDate('due_at', '<', $today)
            ->update(['status' => 'jatuh_tempo', 'updated_at' => now()]);

        $purchases = DB::table('supplier_purchases')
            ->whereIn('payment_status', ['belum_dibayar', 'sebagian'])
            ->whereDate('due_at', '<', $today)
            ->update(['payment_status' => 'jatuh_tempo', 'updated_at' => now()]);

        $this->info("Invoice jatuh tempo: {$invoices}; tagihan supplier jatuh tempo: {$purchases}");

        return self::SUCCESS;
    }
}
