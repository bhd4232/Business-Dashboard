<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ModuleSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            ['module' => 'wholesale_storefront', 'is_active' => true],
            ['module' => 'retail_storefront',    'is_active' => true],
            ['module' => 'reseller_panel',        'is_active' => true],
            ['module' => 'conversation_ai',       'is_active' => true],
            ['module' => 'woocommerce_importer',  'is_active' => false],
        ];

        foreach ($modules as $module) {
            DB::table('module_settings')->updateOrInsert(
                ['module' => $module['module']],
                array_merge($module, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
