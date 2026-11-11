<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tagihan minusan: selisih opname kurang + waste karena kelalaian (tumpah / salah olah)
        Schema::create('outlet_charges', function (Blueprint $t) {
            $t->id();
            $t->string('number', 30)->unique();
            $t->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $t->string('period', 7);                       // Y-m
            $t->date('issued_at');
            $t->date('due_at');
            $t->bigInteger('total')->default(0);
            $t->string('status', 20)->default('belum_dibayar'); // belum_dibayar | lunas
            $t->date('paid_at')->nullable();
            $t->string('notes', 200)->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('outlet_charge_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('outlet_charge_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('stock_request_id')->index();
            $t->foreignId('item_id')->constrained()->cascadeOnDelete();
            $t->string('kind', 10);                        // so | waste
            $t->string('description', 200)->nullable();
            $t->decimal('qty_base', 14, 3);
            $t->bigInteger('unit_price');
            $t->bigInteger('value');
            $t->timestamps();
        });

        Schema::table('stock_requests', function (Blueprint $t) {
            $t->boolean('charged')->default(false)->after('unit_price');          // dibebankan ke outlet
            $t->unsignedBigInteger('charge_id')->nullable()->index()->after('charged'); // sudah masuk tagihan
        });
    }

    public function down(): void
    {
        Schema::table('stock_requests', function (Blueprint $t) {
            $t->dropColumn(['charged', 'charge_id']);
        });
        Schema::dropIfExists('outlet_charge_items');
        Schema::dropIfExists('outlet_charges');
    }
};
