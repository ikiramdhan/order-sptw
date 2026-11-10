<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $t) {
            $t->string('bank_name', 40)->nullable()->after('address');
            $t->string('account_name', 120)->nullable()->after('bank_name');
            $t->string('account_number', 40)->nullable()->after('account_name');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $t) {
            $t->dropColumn(['bank_name', 'account_name', 'account_number']);
        });
    }
};
