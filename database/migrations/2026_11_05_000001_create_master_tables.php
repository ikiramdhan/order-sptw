<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Uang disimpan sebagai bilangan bulat rupiah (tanpa desimal).
        // Qty disimpan desimal(14,3); stok selalu dalam satuan dasar barang.

        Schema::create('outlets', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('address')->nullable();
            $t->string('pic_name')->nullable();
            $t->string('phone', 30)->nullable();
            $t->unsignedSmallInteger('payment_term_days')->default(7); // 0 = tunai
            $t->bigInteger('credit_balance')->default(0);              // saldo kredit dari Nota Retur
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('units', function (Blueprint $t) {
            $t->id();
            $t->string('name', 30)->unique(); // PCS, Dus, Box, Kg ...
            $t->timestamps();
        });

        Schema::create('suppliers', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('phone', 30)->nullable();
            $t->string('address')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('items', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('category', 60)->nullable();
            $t->foreignId('base_unit_id')->constrained('units');
            $t->bigInteger('price')->default(0);                 // harga jual per satuan dasar
            $t->decimal('stock', 14, 3)->default(0);             // dalam satuan dasar
            $t->decimal('min_stock', 14, 3)->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        // Konversi satuan: factor = berapa satuan dasar dalam 1 satuan ini (1 Dus = 40 PCS -> 40)
        Schema::create('item_units', function (Blueprint $t) {
            $t->id();
            $t->foreignId('item_id')->constrained()->cascadeOnDelete();
            $t->foreignId('unit_id')->constrained('units');
            $t->decimal('factor', 14, 3)->default(1);
            $t->boolean('is_sellable')->default(true);
            $t->boolean('is_purchasable')->default(true);
            $t->timestamps();
            $t->unique(['item_id', 'unit_id']);
        });

        Schema::create('settings', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->text('value')->nullable();
            $t->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action', 60);
            $t->string('description')->nullable();
            $t->string('subject_type', 60)->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamps();
            $t->index(['subject_type', 'subject_id']);
        });

        Schema::create('number_sequences', function (Blueprint $t) {
            $t->id();
            $t->string('prefix', 10);          // PO, SJ, INV, RT, NR, PB
            $t->string('period', 4);           // YYMM
            $t->unsignedInteger('last')->default(0);
            $t->unique(['prefix', 'period']);
        });
    }

    public function down(): void
    {
        foreach (['number_sequences', 'activity_logs', 'settings', 'item_units', 'items', 'suppliers', 'units', 'outlets'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
