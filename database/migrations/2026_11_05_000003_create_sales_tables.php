<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // PO status: draft, diajukan, disetujui, dikirim_sebagian, menunggu_backorder, dikirim, diterima, selesai, dibatalkan
        Schema::create('purchase_orders', function (Blueprint $t) {
            $t->id();
            $t->string('number', 30)->unique();
            $t->foreignId('outlet_id')->constrained();
            $t->foreignId('created_by')->constrained('users');
            $t->string('status', 30)->default('draft')->index();
            $t->text('notes')->nullable();
            $t->timestamp('submitted_at')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->foreignId('approved_by')->nullable()->constrained('users');
            $t->string('cancel_reason')->nullable();
            $t->timestamps();
        });

        Schema::create('purchase_order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('item_id')->constrained();
            $t->foreignId('unit_id')->constrained('units');      // satuan yang dipilih outlet
            $t->decimal('qty_input', 14, 3);                      // qty dalam satuan input
            $t->decimal('qty_base', 14, 3);                       // qty dalam satuan dasar
            $t->decimal('qty_shipped_base', 14, 3)->default(0);   // terkirim (satuan dasar)
            $t->decimal('qty_cancelled_base', 14, 3)->default(0); // backorder dibatalkan
            $t->bigInteger('unit_price_base')->default(0);        // harga per satuan dasar saat PO
            $t->timestamps();
        });

        // Surat jalan: tanpa harga
        Schema::create('delivery_notes', function (Blueprint $t) {
            $t->id();
            $t->string('number', 30)->unique();
            $t->foreignId('purchase_order_id')->constrained();
            $t->foreignId('outlet_id')->constrained();
            $t->string('type', 15)->default('regular');           // regular | pengganti_retur
            $t->unsignedBigInteger('return_id')->nullable();
            $t->string('status', 20)->default('dikirim')->index();// dikirim | diterima
            $t->foreignId('created_by')->constrained('users');
            $t->timestamp('shipped_at')->nullable();
            $t->timestamps();
        });

        Schema::create('delivery_note_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('delivery_note_id')->constrained()->cascadeOnDelete();
            $t->foreignId('purchase_order_item_id')->nullable()->constrained('purchase_order_items')->nullOnDelete();
            $t->foreignId('item_id')->constrained();
            $t->foreignId('unit_id')->constrained('units');
            $t->decimal('qty_input', 14, 3);
            $t->decimal('qty_base', 14, 3);
            $t->decimal('qty_received_base', 14, 3)->nullable();  // diisi saat outlet konfirmasi
            $t->timestamps();
        });

        Schema::create('goods_receipts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('delivery_note_id')->constrained();
            $t->foreignId('received_by')->constrained('users');
            $t->timestamp('received_at');
            $t->text('notes')->nullable();                         // catatan selisih
            $t->timestamps();
        });

        // Invoice: terbit per surat jalan yang dikonfirmasi, berdasarkan qty diterima
        // Status: belum_dibayar | sebagian | lunas | jatuh_tempo | dibatalkan
        Schema::create('invoices', function (Blueprint $t) {
            $t->id();
            $t->string('number', 30)->unique();
            $t->foreignId('outlet_id')->constrained();
            $t->foreignId('delivery_note_id')->constrained();
            $t->date('issued_at');
            $t->date('due_at')->index();
            $t->bigInteger('total')->default(0);
            $t->bigInteger('credit_applied')->default(0);          // saldo kredit yang dipakai
            $t->bigInteger('paid')->default(0);
            $t->string('status', 20)->default('belum_dibayar')->index();
            $t->string('cancel_reason')->nullable();
            $t->timestamps();
            $t->index(['outlet_id', 'issued_at']);
        });

        Schema::create('invoice_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $t->foreignId('item_id')->constrained();
            $t->foreignId('unit_id')->constrained('units');
            $t->decimal('qty_input', 14, 3);
            $t->decimal('qty_base', 14, 3);
            $t->bigInteger('unit_price_base');
            $t->bigInteger('subtotal');
            $t->timestamps();
        });

        // Pembayaran masuk dari outlet
        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('invoice_id')->constrained();
            $t->bigInteger('amount');
            $t->date('paid_at');
            $t->string('method', 15)->default('transfer');         // transfer | tunai | lainnya
            $t->string('notes')->nullable();
            $t->foreignId('recorded_by')->constrained('users');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['payments', 'invoice_items', 'invoices', 'goods_receipts', 'delivery_note_items', 'delivery_notes', 'purchase_order_items', 'purchase_orders'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
