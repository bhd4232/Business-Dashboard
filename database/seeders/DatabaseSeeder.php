<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\User;
use App\Support\AdminPassword;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $adminPassword = AdminPassword::fromEnvironment();

        User::query()->updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@example.com')],
            [
                'name' => env('ADMIN_NAME', 'ZamZam Admin'),
                'password' => $adminPassword,
                'role' => 'super_admin',
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        Account::query()->firstOrCreate(
            ['name' => 'Cash'],
            [
                'type' => 'cash',
                'opening_balance' => 0,
                'current_balance' => 0,
                'is_active' => true,
            ],
        );

        ExpenseCategory::query()->firstOrCreate(
            ['slug' => 'general'],
            [
                'name' => 'General',
                'description' => 'Default expense category',
                'is_active' => true,
            ],
        );

        Product::ensureComingSoonPurchaseProducts();
    }
}
