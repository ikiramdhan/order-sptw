<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outlets', function (Blueprint $t) {
            $t->decimal('lat', 10, 7)->nullable();
            $t->decimal('lng', 10, 7)->nullable();
        });
        Schema::table('delivery_notes', function (Blueprint $t) {
            $t->unsignedInteger('route_total_m')->nullable();
        });
        Schema::create('delivery_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('delivery_note_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('role', 20);
            $t->string('body', 300);
            $t->timestamps();
            $t->index(['delivery_note_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_messages');
        Schema::table('delivery_notes', fn (Blueprint $t) => $t->dropColumn('route_total_m'));
        Schema::table('outlets', fn (Blueprint $t) => $t->dropColumn(['lat', 'lng']));
    }
};
