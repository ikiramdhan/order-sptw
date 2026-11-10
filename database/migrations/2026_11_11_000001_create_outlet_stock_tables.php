<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outlet_stocks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $t->foreignId('item_id')->constrained()->cascadeOnDelete();
            $t->decimal('qty', 14, 3)->default(0);
            $t->timestamps();
            $t->unique(['outlet_id', 'item_id']);
        });

        // type: masuk | terjual | so | waste | retur
        Schema::create('outlet_stock_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $t->foreignId('item_id')->constrained()->cascadeOnDelete();
            $t->string('type', 20)->index();
            $t->decimal('qty_base', 14, 3);
            $t->decimal('balance_after', 14, 3);
            $t->date('occurred_on')->index();
            $t->string('ref_type', 40)->nullable();
            $t->unsignedBigInteger('ref_id')->nullable();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('notes', 200)->nullable();
            $t->timestamps();
            $t->index(['ref_type', 'ref_id']);
        });

        Schema::create('outlet_sales_imports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $t->date('sales_date');
            $t->string('filename', 150)->nullable();
            $t->unsignedInteger('rows_ok')->default(0);
            $t->unsignedInteger('rows_skipped')->default(0);
            $t->boolean('is_voided')->default(false);
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
        });

        // Waste (CK atau outlet) dan Stok Opname outlet. outlet_id null = gudang CK.
        Schema::create('stock_requests', function (Blueprint $t) {
            $t->id();
            $t->string('type', 10)->index();            // waste | so
            $t->foreignId('outlet_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('item_id')->constrained()->cascadeOnDelete();
            $t->decimal('qty_base', 14, 3);             // waste: jumlah dibuang; so: hasil hitung fisik
            $t->decimal('qty_system', 14, 3)->nullable();
            $t->string('reason', 20)->nullable();
            $t->string('notes', 200)->nullable();
            $t->string('status', 12)->default('menunggu')->index(); // menunggu | disetujui | ditolak
            $t->foreignId('created_by')->constrained('users');
            $t->foreignId('decided_by')->nullable()->constrained('users');
            $t->timestamp('decided_at')->nullable();
            $t->string('decision_note', 200)->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_requests');
        Schema::dropIfExists('outlet_sales_imports');
        Schema::dropIfExists('outlet_stock_movements');
        Schema::dropIfExists('outlet_stocks');
    }
};
