<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\TransactionLedger;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ReportService
{
    public function dateRange(?string $from = null, ?string $to = null): array
    {
        return [
            $from ? Carbon::parse($from)->startOfDay() : now()->startOfDay(),
            $to ? Carbon::parse($to)->endOfDay() : now()->endOfDay(),
        ];
    }

    public function dashboardSummary(): array
    {
        [$from, $to] = $this->dateRange();

        return [
            'sales_today' => $this->salesQuery($from, $to)->sum('total_amount'),
            'purchases_today' => $this->purchasesQuery($from, $to)->sum('total_amount'),
            'customer_payments_today' => TransactionLedger::query()
                ->where('type', 'customer_payment')
                ->whereDate('transaction_date', '>=', $from->toDateString())
                ->whereDate('transaction_date', '<=', $to->toDateString())
                ->sum('amount'),
            'supplier_payments_today' => TransactionLedger::query()
                ->where('type', 'supplier_payment')
                ->whereDate('transaction_date', '>=', $from->toDateString())
                ->whereDate('transaction_date', '<=', $to->toDateString())
                ->sum('amount'),
            'expenses_today' => $this->expensesQuery($from, $to)->sum('amount'),
            'customer_due' => Customer::query()->sum('current_balance'),
            'supplier_due' => Supplier::query()->sum('current_balance'),
            'low_stock_count' => Product::query()->whereColumn('stock', '<=', 'reorder_level')->count(),
            'coming_soon_count' => Product::query()->where('status', Product::STATUS_COMING_SOON)->count(),
            'account_balance' => Account::query()->sum('current_balance'),
        ];
    }

    public function sales(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return $this->salesQuery($from, $to)
            ->with('customer')
            ->orderByDesc('order_date')
            ->get();
    }

    public function purchases(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return $this->purchasesQuery($from, $to)
            ->with('supplier')
            ->orderByDesc('purchase_date')
            ->get();
    }

    public function expenses(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return $this->expensesQuery($from, $to)
            ->with(['category', 'account'])
            ->orderByDesc('expense_date')
            ->get();
    }

    public function ledger(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return TransactionLedger::query()
            ->with('account')
            ->whereDate('transaction_date', '>=', $from->toDateString())
            ->whereDate('transaction_date', '<=', $to->toDateString())
            ->orderByDesc('transaction_date')
            ->get();
    }

    public function stock(): Collection
    {
        return Product::query()
            ->with('category')
            ->orderBy('name')
            ->get();
    }

    public function lowStock(): Collection
    {
        return Product::query()
            ->with('category')
            ->whereColumn('stock', '<=', 'reorder_level')
            ->orderBy('name')
            ->get();
    }

    public function customerDues(): Collection
    {
        return Customer::query()
            ->where('current_balance', '>', 0)
            ->orderByDesc('current_balance')
            ->get();
    }

    public function supplierDues(): Collection
    {
        return Supplier::query()
            ->where('current_balance', '>', 0)
            ->orderByDesc('current_balance')
            ->get();
    }

    public function profit(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return $this->salesQuery($from, $to)
            ->with('items.product')
            ->get()
            ->flatMap(fn (Order $order) => $order->items->map(function ($item) use ($order): array {
                $cost = (float) ($item->product?->cost_price ?? 0) * (int) $item->quantity;
                $revenue = (float) $item->subtotal;

                return [
                    'date' => optional($order->order_date)->toDateString(),
                    'invoice' => $order->order_number,
                    'product' => $item->product?->name,
                    'quantity' => $item->quantity,
                    'revenue' => $revenue,
                    'cost' => $cost,
                    'profit' => $revenue - $cost,
                ];
            }));
    }

    protected function salesQuery(CarbonInterface $from, CarbonInterface $to): Builder
    {
        return Order::query()
            ->whereIn('status', ['confirmed', 'completed'])
            ->whereDate('order_date', '>=', $from->toDateString())
            ->whereDate('order_date', '<=', $to->toDateString());
    }

    protected function purchasesQuery(CarbonInterface $from, CarbonInterface $to): Builder
    {
        return Purchase::query()
            ->where('status', 'received')
            ->whereDate('purchase_date', '>=', $from->toDateString())
            ->whereDate('purchase_date', '<=', $to->toDateString());
    }

    protected function expensesQuery(CarbonInterface $from, CarbonInterface $to): Builder
    {
        return Expense::query()
            ->whereDate('expense_date', '>=', $from->toDateString())
            ->whereDate('expense_date', '<=', $to->toDateString());
    }
}
