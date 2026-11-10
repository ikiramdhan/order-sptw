<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['PCS', 'Dus', 'Box', 'Pak', 'Kg', 'Karung', 'Liter'] as $name) {
            Unit::firstOrCreate(['name' => $name]);
        }
    }
}
