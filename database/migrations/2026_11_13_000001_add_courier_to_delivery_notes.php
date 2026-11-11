<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_notes', function (Blueprint $t) {
            $t->foreignId('courier_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('courier_status', 20)->nullable();      // ditugaskan | diantar | sampai
            $t->timestamp('courier_started_at')->nullable();
            $t->timestamp('courier_arrived_at')->nullable();
            $t->decimal('last_lat', 10, 7)->nullable();
            $t->decimal('last_lng', 10, 7)->nullable();
            $t->unsignedInteger('last_accuracy')->nullable();
            $t->timestamp('last_loc_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('delivery_notes', function (Blueprint $t) {
            $t->dropConstrainedForeignId('courier_id');
            $t->dropColumn(['courier_status', 'courier_started_at', 'courier_arrived_at', 'last_lat', 'last_lng', 'last_accuracy', 'last_loc_at']);
        });
    }
};
