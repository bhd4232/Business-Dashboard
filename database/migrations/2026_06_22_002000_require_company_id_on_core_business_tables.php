<?php

use App\Models\Company;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'categories',
        'products',
        'stock_movements',
        'suppliers',
        'purchases',
        'purchase_items',
        'customers',
        'orders',
        'order_items',
        'customer_payments',
        'supplier_payments',
        'accounts',
        'expenses',
        'expense_categories',
        'transaction_ledgers',
        'audit_logs',
    ];

    public function up(): void
    {
        $companyId = Company::defaultCompanyId();

        foreach (self::TABLES as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'company_id')) {
                continue;
            }

            if ($companyId) {
                DB::table($tableName)
                    ->whereNull('company_id')
                    ->update(['company_id' => $companyId]);
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('company_id')->nullable(false)->change();
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::TABLES) as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'company_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('company_id')->nullable()->change();
            });
        }
    }
};
