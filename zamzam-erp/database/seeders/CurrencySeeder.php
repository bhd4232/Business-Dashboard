<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $currencies = [
            ['code' => 'BDT', 'name' => 'Bangladesh Taka',    'symbol' => '৳',  'is_base' => true,  'decimal_places' => 2],
            ['code' => 'CNY', 'name' => 'Chinese Yuan',        'symbol' => '¥',  'is_base' => false, 'decimal_places' => 2],
            ['code' => 'USD', 'name' => 'US Dollar',           'symbol' => '$',  'is_base' => false, 'decimal_places' => 2],
        ];

        foreach ($currencies as $currency) {
            DB::table('currencies')->updateOrInsert(
                ['code' => $currency['code']],
                array_merge($currency, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
