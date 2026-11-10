<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $t) {
            $t->string('code', 30)->nullable()->after('id')->index();
            $t->unsignedInteger('retail_price')->nullable()->after('price'); // harga outlet ke konsumen (Harga CS)
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $t) {
            $t->dropColumn(['code', 'retail_price']);
        });
    }
};
