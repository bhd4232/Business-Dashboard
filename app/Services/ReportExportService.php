<?php

namespace App\Services;

use App\Models\Purchase;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportService
{
    public function __construct(
        protected ReportService $reports,
    ) {}

    public function download(string $type, ?string $dateFrom = null, ?string $dateTo = null): StreamedResponse
    {
        [$from, $to] = $this->reports->dateRange($dateFrom, $dateTo);

        $exports = $this->exports($from, $to);

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
    }

    protected function exports($from, $to): array
    {
        return [
            'sales' => [
                'filename' => 'sales-report.csv',
                'headings' => ['Date', 'Invoice', 'Customer', 'Total Amount', 'Paid Amount', 'Due Amount', 'Status'],
                'rows' => fn () => $this->reports->sales($from, $to)->map(fn ($order): array => [
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
                'headings' => function () use ($from, $to): array {
                    $customCostLabels = $this->purchaseCustomCostLabels($this->reports->purchases($from, $to));

                    return [
                        'Date',
                        'Purchase',
                        'Supplier',
                        ...Purchase::CHINA_TO_BD_COST_FIELDS,
                        ...$customCostLabels,
                        'China to BD Cost Total',
                        'Landed Cost Total',
                        'Total Amount',
                        'Paid Amount',
                        'Due Amount',
                        'Status',
                    ];
                },
                'rows' => function () use ($from, $to) {
                    $purchases = $this->reports->purchases($from, $to);
                    $customCostLabels = $this->purchaseCustomCostLabels($purchases);

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
                        $purchase->landedCostTotal(),
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
                'rows' => fn () => $this->reports->profit($from, $to)->map(fn (array $row): array => [
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
                'rows' => fn () => $this->reports->stock()->map(fn ($product): array => [
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
                'rows' => fn () => $this->reports->lowStock()->map(fn ($product): array => [
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
                'rows' => fn () => $this->reports->customerDues()->map(fn ($customer): array => [
                    $customer->name,
                    $customer->phone,
                    $customer->current_balance,
                ]),
            ],
            'supplier-dues' => [
                'filename' => 'supplier-due-report.csv',
                'headings' => ['Supplier', 'Phone', 'Company', 'Current Balance'],
                'rows' => fn () => $this->reports->supplierDues()->map(fn ($supplier): array => [
                    $supplier->name,
                    $supplier->phone,
                    $supplier->company_name,
                    $supplier->current_balance,
                ]),
            ],
            'expenses' => [
                'filename' => 'expense-report.csv',
                'headings' => ['Date', 'Expense Number', 'Category', 'Account', 'Amount'],
                'rows' => fn () => $this->reports->expenses($from, $to)->map(fn ($expense): array => [
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
                'rows' => fn () => $this->reports->ledger($from, $to)->map(fn ($entry): array => [
                    optional($entry->transaction_date)->toDateString(),
                    $entry->account?->name,
                    $entry->type,
                    $entry->direction,
                    $entry->amount,
                    $entry->note,
                ]),
            ],
        ];
    }

    protected function purchaseCustomCostLabels(Collection $purchases): array
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
