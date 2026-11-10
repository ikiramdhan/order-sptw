<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_recipes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('item_id')->constrained()->cascadeOnDelete();
            $t->foreignId('ingredient_id')->constrained('items');
            $t->decimal('qty_base', 14, 4); // bahan per 1 satuan dasar barang jadi
            $t->timestamps();
            $t->unique(['item_id', 'ingredient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_recipes');
    }
};
