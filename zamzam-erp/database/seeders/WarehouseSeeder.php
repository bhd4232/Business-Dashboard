<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WarehouseSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('warehouses')->updateOrInsert(
            ['code' => 'WH-MAIN'],
            [
                'name'       => 'Main Warehouse',
                'code'       => 'WH-MAIN',
                'address'    => 'Dhaka, Bangladesh',
                'city'       => 'Dhaka',
                'is_default' => true,
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
