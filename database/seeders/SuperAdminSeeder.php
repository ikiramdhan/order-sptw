<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Membuat satu akun Super Admin awal.
 * Atur lewat .env: SUPERADMIN_EMAIL, SUPERADMIN_NAME, SUPERADMIN_PASSWORD
 * Password wajib diganti saat login pertama.
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('SUPERADMIN_EMAIL', 'superadmin@seblaktehwanti.com');

        if (User::where('email', $email)->exists()) {
            return;
        }

        User::create([
            'name' => env('SUPERADMIN_NAME', 'Super Admin'),
            'email' => $email,
            'password' => env('SUPERADMIN_PASSWORD', 'GantiSaya123'),
            'role' => User::ROLE_SUPER_ADMIN,
            'is_active' => true,
            'must_change_password' => true,
        ]);
    }
}
