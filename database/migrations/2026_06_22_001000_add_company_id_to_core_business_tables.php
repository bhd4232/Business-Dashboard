<?php

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
        $companyId = (int) DB::table('companies')->where('slug', 'main-company')->value('id');

        foreach (self::TABLES as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'company_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('company_id')
                    ->nullable()
                    ->after('id')
                    ->constrained()
                    ->restrictOnDelete();
            });

            DB::table($tableName)->update(['company_id' => $companyId]);
        }

        $this->addCompanyIndexes();
    }

    public function down(): void
    {
        foreach (array_reverse(self::TABLES) as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'company_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('company_id');
            });
        }
    }

    protected function addCompanyIndexes(): void
    {
        $indexes = [
            'categories' => ['company_id', 'slug'],
            'products' => ['company_id', 'sku'],
            'stock_movements' => ['company_id', 'product_id', 'type'],
            'suppliers' => ['company_id', 'name'],
            'purchases' => ['company_id', 'purchase_date'],
            'purchase_items' => ['company_id', 'purchase_id'],
            'customers' => ['company_id', 'name'],
            'orders' => ['company_id', 'order_date'],
            'order_items' => ['company_id', 'order_id'],
            'customer_payments' => ['company_id', 'payment_date'],
            'supplier_payments' => ['company_id', 'payment_date'],
            'accounts' => ['company_id', 'name'],
            'expenses' => ['company_id', 'expense_date'],
            'expense_categories' => ['company_id', 'name'],
            'transaction_ledgers' => ['company_id', 'transaction_date'],
            'audit_logs' => ['company_id', 'created_at'],
        ];

        foreach ($indexes as $tableName => $columns) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($columns, $tableName): void {
                $table->index($columns, "{$tableName}_company_lookup_index");
            });
        }
    }
};
