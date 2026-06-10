<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CurrencySeeder::class,
            RolesPermissionsSeeder::class,
            AdminUserSeeder::class,
            ModuleSettingsSeeder::class,
            IdFormatSeeder::class,
            WarehouseSeeder::class,
            ChartOfAccountsSeeder::class,
            // Phase 2 seeders
            CategorySeeder::class,
            SupplierSeeder::class,
        ]);
    }
}
