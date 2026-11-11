<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_requests', function (Blueprint $t) {
            $t->bigInteger('unit_price')->nullable()->after('qty_system'); // harga CK per satuan dasar saat disetujui
        });
    }

    public function down(): void
    {
        Schema::table('stock_requests', function (Blueprint $t) {
            $t->dropColumn('unit_price');
        });
    }
};
