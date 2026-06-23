<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CustomerPayment;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Purchase;
use App\Models\SupplierPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class CompanyDataMigrationService
{
    /** @var array<string, string> */
    public const ROOT_TABLES = [
        'categories' => 'categories',
        'products' => 'products',
        'suppliers' => 'suppliers',
        'purchases' => 'purchases',
        'customers' => 'customers',
        'orders' => 'orders',
        'accounts' => 'accounts',
        'expense_categories' => 'expense_categories',
        'expenses' => 'expenses',
    ];

    /** @return array{company: Company, counts: array<string, int>, ids: array<string, list<int>>} */
    public function inspect(string $companySlug, array $mapping): array
    {
        $company = Company::query()->where('slug', $companySlug)->firstOrFail();
        $ids = [];
        $counts = [];

        foreach (self::ROOT_TABLES as $key => $table) {
            $selected = collect($mapping[$key] ?? [])->map(fn ($id): int => (int) $id)->filter()->unique()->values();
            $existing = $selected->isEmpty() ? collect() : DB::table($table)->whereIn('id', $selected)->pluck('id');

            if ($existing->count() !== $selected->count()) {
                throw ValidationException::withMessages([$key => "One or more selected {$key} records do not exist."]);
            }

            $ids[$key] = $existing->map(fn ($id): int => (int) $id)->all();
            $counts[$key] = $existing->count();
        }

        $this->expandChildren($ids, $counts);

        return compact('company', 'counts', 'ids');
    }

    /** @return array<string, int> */
    public function migrate(string $companySlug, array $mapping): array
    {
        $plan = $this->inspect($companySlug, $mapping);
        $companyId = $plan['company']->getKey();

        DB::transaction(function () use ($plan, $companyId): void {
            foreach ($plan['ids'] as $table => $ids) {
                if ($ids !== [] && Schema::hasColumn($table, 'company_id')) {
                    DB::table($table)->whereIn('id', $ids)->update(['company_id' => $companyId]);
                }
            }

            $this->assertRelationshipConsistency($companyId, $plan['ids']);
        });

        return $plan['counts'];
    }

    protected function expandChildren(array &$ids, array &$counts): void
    {
        $relations = [
            'purchase_items' => ['purchase_id', 'purchases'],
            'order_items' => ['order_id', 'orders'],
            'stock_movements' => ['product_id', 'products'],
            'customer_payments' => ['customer_id', 'customers'],
            'supplier_payments' => ['supplier_id', 'suppliers'],
        ];

        foreach ($relations as $table => [$foreignKey, $parent]) {
            $parentIds = $ids[$parent] ?? [];
            $childIds = $parentIds === [] ? collect() : DB::table($table)->whereIn($foreignKey, $parentIds)->pluck('id');
            $ids[$table] = $childIds->map(fn ($id): int => (int) $id)->all();
            $counts[$table] = $childIds->count();
        }

        $ledgerIds = collect();
        $ledgerSources = [
            'orders' => Order::class,
            'purchases' => Purchase::class,
            'expenses' => Expense::class,
            'customer_payments' => CustomerPayment::class,
            'supplier_payments' => SupplierPayment::class,
        ];
        foreach ($ledgerSources as $source => $modelClass) {
            $sourceIds = $ids[$source] ?? [];
            if ($sourceIds !== []) {
                $ledgerIds = $ledgerIds->merge(DB::table('transaction_ledgers')
                    ->where('reference_type', $modelClass)
                    ->whereIn('reference_id', $sourceIds)
                    ->pluck('id'));
            }
        }

        if (($ids['accounts'] ?? []) !== []) {
            $ledgerIds = $ledgerIds->merge(DB::table('transaction_ledgers')
                ->whereIn('account_id', $ids['accounts'])
                ->pluck('id'));
        }
        $ids['transaction_ledgers'] = $ledgerIds->unique()->map(fn ($id): int => (int) $id)->values()->all();
        $counts['transaction_ledgers'] = count($ids['transaction_ledgers']);
    }

    protected function assertRelationshipConsistency(int $companyId, array $ids): void
    {
        $checks = [
            ['purchase_items', 'purchase_id', 'purchases'],
            ['order_items', 'order_id', 'orders'],
            ['stock_movements', 'product_id', 'products'],
            ['customer_payments', 'customer_id', 'customers'],
            ['supplier_payments', 'supplier_id', 'suppliers'],
        ];

        foreach ($checks as [$child, $foreignKey, $parent]) {
            if (($ids[$child] ?? []) === []) {
                continue;
            }

            $mismatch = DB::table("{$child} as child")
                ->join("{$parent} as parent", 'parent.id', '=', "child.{$foreignKey}")
                ->whereIn('child.id', $ids[$child])
                ->where(fn ($query) => $query->where('child.company_id', '!=', $companyId)
                    ->orWhereColumn('child.company_id', '!=', 'parent.company_id'))
                ->exists();

            if ($mismatch) {
                throw ValidationException::withMessages(['mapping' => "Company mismatch detected in {$child}."]);
            }
        }
    }
}
