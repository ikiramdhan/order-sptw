<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            // super_admin | admin_ck | gudang | outlet
            $t->string('role', 20)->default('outlet')->after('email');
            $t->foreignId('outlet_id')->nullable()->after('role')->constrained('outlets')->nullOnDelete();
            $t->boolean('is_active')->default(true)->after('outlet_id');
            $t->boolean('must_change_password')->default(false)->after('is_active');
            $t->timestamp('last_login_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('outlet_id');
            $t->dropColumn(['role', 'is_active', 'must_change_password', 'last_login_at']);
        });
    }
};
