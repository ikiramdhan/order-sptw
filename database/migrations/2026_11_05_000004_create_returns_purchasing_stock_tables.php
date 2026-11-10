<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Retur status: diajukan | disetujui | ditolak | selesai
        // Resolusi: potong_tagihan | barang_pengganti
        Schema::create('returns', function (Blueprint $t) {
            $t->id();
            $t->string('number', 30)->unique();
            $t->foreignId('outlet_id')->constrained();
            $t->foreignId('delivery_note_id')->constrained();
            $t->foreignId('invoice_id')->nullable()->constrained();
            $t->foreignId('requested_by')->constrained('users');
            $t->string('status', 20)->default('diajukan')->index();
            $t->string('resolution', 20)->nullable();
            $t->text('notes')->nullable();
            $t->string('reject_reason')->nullable();
            $t->foreignId('decided_by')->nullable()->constrained('users');
            $t->timestamp('decided_at')->nullable();
            $t->timestamps();
        });

        Schema::create('return_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('return_id')->constrained('returns')->cascadeOnDelete();
            $t->foreignId('item_id')->constrained();
            $t->foreignId('unit_id')->constrained('units');
            $t->decimal('qty_input', 14, 3);
            $t->decimal('qty_base', 14, 3);
            $t->string('reason', 40);                              // rusak | tidak_sesuai | kedaluwarsa
            $t->string('disposition', 15)->nullable();             // masuk_stok | dibuang
            $t->bigInteger('value')->default(0);                   // nilai retur (rupiah)
            $t->timestamps();
        });

        // Nota Retur: potongan tagihan atau saldo kredit
        Schema::create('credit_notes', function (Blueprint $t) {
            $t->id();
            $t->string('number', 30)->unique();
            $t->foreignId('return_id')->constrained('returns');
            $t->foreignId('outlet_id')->constrained();
            $t->foreignId('invoice_id')->nullable()->constrained();
            $t->bigInteger('amount');
            $t->bigInteger('applied_to_invoice')->default(0);
            $t->bigInteger('to_balance')->default(0);              // masuk saldo kredit outlet
            $t->date('issued_at');
            $t->timestamps();
        });

        // Pembelian CK ke supplier. Status bayar: belum_dibayar | sebagian | lunas | jatuh_tempo
        Schema::create('supplier_purchases', function (Blueprint $t) {
            $t->id();
            $t->string('number', 30)->unique();
            $t->foreignId('supplier_id')->constrained();
            $t->date('purchased_at');
            $t->date('due_at')->index();
            $t->bigInteger('total')->default(0);
            $t->bigInteger('paid')->default(0);
            $t->string('payment_status', 20)->default('belum_dibayar')->index();
            $t->timestamp('received_at')->nullable();              // stok bertambah saat diterima
            $t->foreignId('created_by')->constrained('users');
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        Schema::create('supplier_purchase_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('supplier_purchase_id')->constrained()->cascadeOnDelete();
            $t->foreignId('item_id')->constrained();
            $t->foreignId('unit_id')->constrained('units');
            $t->decimal('qty_input', 14, 3);
            $t->decimal('qty_base', 14, 3);
            $t->bigInteger('unit_price');                          // harga beli per satuan input
            $t->bigInteger('subtotal');
            $t->timestamps();
        });

        Schema::create('supplier_payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('supplier_purchase_id')->constrained();
            $t->bigInteger('amount');
            $t->date('paid_at');
            $t->string('method', 15)->default('transfer');
            $t->string('notes')->nullable();
            $t->foreignId('recorded_by')->constrained('users');
            $t->timestamps();
        });

        // Mutasi stok (selalu dalam satuan dasar; qty bertanda + masuk / - keluar)
        // type: pembelian | penjualan | retur_masuk | retur_pengganti | penyesuaian
        Schema::create('stock_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('item_id')->constrained();
            $t->string('type', 20)->index();
            $t->decimal('qty_base', 14, 3);
            $t->decimal('balance_after', 14, 3)->nullable();
            $t->string('ref_type', 40)->nullable();
            $t->unsignedBigInteger('ref_id')->nullable();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('notes')->nullable();
            $t->timestamps();
            $t->index(['ref_type', 'ref_id']);
        });

        Schema::create('stock_adjustments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('item_id')->constrained();
            $t->decimal('qty_system', 14, 3);
            $t->decimal('qty_physical', 14, 3);
            $t->decimal('difference', 14, 3);
            $t->string('reason', 20);                               // opname | rusak | hilang | lainnya
            $t->string('notes')->nullable();
            $t->foreignId('user_id')->constrained();
            $t->timestamps();
        });

        // Notifikasi di dalam aplikasi
        Schema::create('app_notifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('title');
            $t->text('body')->nullable();
            $t->string('url')->nullable();
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
            $t->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        foreach (['app_notifications', 'stock_adjustments', 'stock_movements', 'supplier_payments', 'supplier_purchase_items', 'supplier_purchases', 'credit_notes', 'return_items', 'returns'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
