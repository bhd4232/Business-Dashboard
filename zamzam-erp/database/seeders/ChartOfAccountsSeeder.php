<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            // Assets
            ['code' => '1000', 'name' => 'Assets',                    'type' => 'asset',     'parent_id' => null],
            ['code' => '1100', 'name' => 'Cash & Bank',               'type' => 'asset',     'parent_id' => 1],
            ['code' => '1101', 'name' => 'Cash in Hand',              'type' => 'asset',     'parent_id' => 2],
            ['code' => '1102', 'name' => 'Bank Account',              'type' => 'asset',     'parent_id' => 2],
            ['code' => '1200', 'name' => 'Accounts Receivable',       'type' => 'asset',     'parent_id' => 1],
            ['code' => '1300', 'name' => 'Inventory',                 'type' => 'asset',     'parent_id' => 1],
            // Liabilities
            ['code' => '2000', 'name' => 'Liabilities',               'type' => 'liability', 'parent_id' => null],
            ['code' => '2100', 'name' => 'Accounts Payable',          'type' => 'liability', 'parent_id' => 7],
            ['code' => '2200', 'name' => 'Short-term Loans',          'type' => 'liability', 'parent_id' => 7],
            // Equity
            ['code' => '3000', 'name' => 'Equity',                    'type' => 'equity',    'parent_id' => null],
            ['code' => '3100', 'name' => "Owner's Capital",           'type' => 'equity',    'parent_id' => 10],
            ['code' => '3200', 'name' => 'Retained Earnings',         'type' => 'equity',    'parent_id' => 10],
            // Revenue
            ['code' => '4000', 'name' => 'Revenue',                   'type' => 'revenue',   'parent_id' => null],
            ['code' => '4100', 'name' => 'Wholesale Sales Revenue',   'type' => 'revenue',   'parent_id' => 13],
            ['code' => '4200', 'name' => 'Retail Sales Revenue',      'type' => 'revenue',   'parent_id' => 13],
            ['code' => '4300', 'name' => 'Other Income',              'type' => 'revenue',   'parent_id' => 13],
            // Expenses
            ['code' => '5000', 'name' => 'Expenses',                  'type' => 'expense',   'parent_id' => null],
            ['code' => '5100', 'name' => 'Cost of Goods Sold',        'type' => 'expense',   'parent_id' => 17],
            ['code' => '5200', 'name' => 'Shipping & Freight',        'type' => 'expense',   'parent_id' => 17],
            ['code' => '5300', 'name' => 'Salaries & Wages',          'type' => 'expense',   'parent_id' => 17],
            ['code' => '5400', 'name' => 'Office & Admin',            'type' => 'expense',   'parent_id' => 17],
            ['code' => '5500', 'name' => 'Marketing & Advertising',   'type' => 'expense',   'parent_id' => 17],
        ];

        foreach ($accounts as $index => $account) {
            DB::table('chart_of_accounts')->updateOrInsert(
                ['code' => $account['code']],
                array_merge($account, [
                    'is_active'  => true,
                    'sort_order' => $index,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
