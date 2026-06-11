<?php

use App\Services\ReportService;
use App\Models\Order;
use App\Models\Purchase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->get('/admin/orders/{order}/print', function (Order $order) {
    return view('orders.print', [
        'order' => $order->load(['customer', 'items.product']),
    ]);
})->name('orders.print');

Route::middleware('auth')->get('/admin/reports/export/{type}', function (string $type, Request $request, ReportService $reports) {
    abort_unless($request->user()?->canExportReports(), 403);

    [$from, $to] = $reports->dateRange($request->query('date_from'), $request->query('date_to'));

    $exports = [
        'sales' => [
            'filename' => 'sales-report.csv',
            'headings' => ['Date', 'Invoice', 'Customer', 'Total Amount', 'Paid Amount', 'Due Amount', 'Status'],
            'rows' => fn () => $reports->sales($from, $to)->map(fn ($order): array => [
                optional($order->order_date)->toDateString(),
                $order->order_number,
                $order->customer?->name,
                $order->total_amount,
                $order->paid_amount,
                $order->due_amount,
                $order->status,
            ]),
        ],
        'purchases' => [
            'filename' => 'purchase-report.csv',
            'headings' => function () use ($reports, $from, $to): array {
                $customCostLabels = purchaseCustomCostLabels($reports->purchases($from, $to));

                return [
                    'Date',
                    'Purchase',
                    'Supplier',
                    ...Purchase::CHINA_TO_BD_COST_FIELDS,
                    ...$customCostLabels,
                    'China to BD Cost Total',
                    'Total Amount',
                    'Paid Amount',
                    'Due Amount',
                    'Status',
                ];
            },
            'rows' => function () use ($reports, $from, $to) {
                $purchases = $reports->purchases($from, $to);
                $customCostLabels = purchaseCustomCostLabels($purchases);

                return $purchases->map(fn ($purchase): array => [
                    optional($purchase->purchase_date)->toDateString(),
                    $purchase->purchase_number,
                    $purchase->supplier?->name,
                    ...collect(Purchase::CHINA_TO_BD_COST_FIELDS)
                        ->keys()
                        ->map(fn (string $field): string => (string) $purchase->{$field})
                        ->all(),
                    ...collect($customCostLabels)
                        ->map(fn (string $label): float => $purchase->customCostAmountFor($label))
                        ->all(),
                    $purchase->chinaToBdCostTotal(),
                    $purchase->total_amount,
                    $purchase->paid_amount,
                    $purchase->due_amount,
                    $purchase->status,
                ]);
            },
        ],
        'profit' => [
            'filename' => 'product-profit-report.csv',
            'headings' => ['Date', 'Invoice', 'Product', 'Quantity', 'Revenue', 'Cost', 'Profit'],
            'rows' => fn () => $reports->profit($from, $to)->map(fn (array $row): array => [
                $row['date'],
                $row['invoice'],
                $row['product'],
                $row['quantity'],
                $row['revenue'],
                $row['cost'],
                $row['profit'],
            ]),
        ],
        'stock' => [
            'filename' => 'stock-report.csv',
            'headings' => ['SKU', 'Product', 'Category', 'Stock', 'Reorder Level', 'Cost Price', 'Sale Price'],
            'rows' => fn () => $reports->stock()->map(fn ($product): array => [
                $product->sku,
                $product->name,
                $product->category?->name,
                $product->stock,
                $product->reorder_level,
                $product->cost_price,
                $product->sale_price ?? $product->price,
            ]),
        ],
        'low-stock' => [
            'filename' => 'low-stock-report.csv',
            'headings' => ['SKU', 'Product', 'Category', 'Stock', 'Reorder Level', 'Cost Price', 'Sale Price'],
            'rows' => fn () => $reports->lowStock()->map(fn ($product): array => [
                $product->sku,
                $product->name,
                $product->category?->name,
                $product->stock,
                $product->reorder_level,
                $product->cost_price,
                $product->sale_price ?? $product->price,
            ]),
        ],
        'customer-dues' => [
            'filename' => 'customer-due-report.csv',
            'headings' => ['Customer', 'Phone', 'Current Balance'],
            'rows' => fn () => $reports->customerDues()->map(fn ($customer): array => [
                $customer->name,
                $customer->phone,
                $customer->current_balance,
            ]),
        ],
        'supplier-dues' => [
            'filename' => 'supplier-due-report.csv',
            'headings' => ['Supplier', 'Phone', 'Company', 'Current Balance'],
            'rows' => fn () => $reports->supplierDues()->map(fn ($supplier): array => [
                $supplier->name,
                $supplier->phone,
                $supplier->company_name,
                $supplier->current_balance,
            ]),
        ],
        'expenses' => [
            'filename' => 'expense-report.csv',
            'headings' => ['Date', 'Expense Number', 'Category', 'Account', 'Amount'],
            'rows' => fn () => $reports->expenses($from, $to)->map(fn ($expense): array => [
                optional($expense->expense_date)->toDateString(),
                $expense->expense_number,
                $expense->category?->name,
                $expense->account?->name,
                $expense->amount,
            ]),
        ],
        'ledger' => [
            'filename' => 'account-transaction-report.csv',
            'headings' => ['Date', 'Account', 'Type', 'Direction', 'Amount', 'Note'],
            'rows' => fn () => $reports->ledger($from, $to)->map(fn ($entry): array => [
                optional($entry->transaction_date)->toDateString(),
                $entry->account?->name,
                $entry->type,
                $entry->direction,
                $entry->amount,
                $entry->note,
            ]),
        ],
    ];

    abort_unless(array_key_exists($type, $exports), 404);

    $export = $exports[$type];

    return response()->streamDownload(function () use ($export): void {
        $handle = fopen('php://output', 'w');
        $headings = is_callable($export['headings']) ? $export['headings']() : $export['headings'];

        fputcsv($handle, $headings);

        foreach ($export['rows']() as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);
    }, $export['filename'], [
        'Content-Type' => 'text/csv',
    ]);
})->name('reports.export');

if (! function_exists('purchaseCustomCostLabels')) {
    function purchaseCustomCostLabels($purchases): array
    {
        return $purchases
            ->flatMap(fn ($purchase): array => collect($purchase->custom_costs ?? [])
                ->pluck('label')
                ->filter()
                ->all())
            ->unique()
            ->values()
            ->all();
    }
}
