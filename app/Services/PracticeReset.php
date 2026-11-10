<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/** Menghapus data latihan. Urutan hapus mengikuti relasi (anak dulu, induk belakangan). */
class PracticeReset
{
    public const TRANSACTIONS = 'transaksi';
    public const ALL = 'semua';

    /** Tabel transaksi, urut aman terhadap foreign key. */
    private const TX_TABLES = [
        'stock_requests', 'outlet_sales_imports', 'outlet_stock_movements', 'outlet_stocks', 'app_notifications', 'credit_notes', 'payments', 'invoice_items', 'return_items', 'returns',
        'invoices', 'goods_receipts', 'delivery_note_items', 'delivery_notes',
        'purchase_order_items', 'purchase_orders',
        'supplier_payments', 'supplier_purchase_items', 'supplier_purchases',
        'stock_adjustments', 'stock_movements', 'activity_logs', 'number_sequences',
    ];

    public static function run(string $scope): void
    {
        DB::transaction(function () use ($scope) {
            foreach (self::TX_TABLES as $t) {
                DB::table($t)->delete();
            }
            DB::table('items')->update(['stock' => 0]);
            DB::table('outlets')->update(['credit_balance' => 0]);

            if ($scope === self::ALL) {
                DB::table('users')->where('role', 'outlet')->delete();
                DB::table('item_recipes')->delete();
                DB::table('item_units')->delete();
                DB::table('items')->delete();
                DB::table('suppliers')->delete();
                DB::table('outlets')->delete();
            }
        });
    }
}
