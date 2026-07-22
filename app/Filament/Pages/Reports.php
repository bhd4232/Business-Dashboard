<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Reports as ReportsCluster;
use App\Services\ReportService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class Reports extends Page implements HasTable
{
    use InteractsWithTable;

    public const REPORTS = [
        'sales' => [
            'title' => 'Sales Report',
            'description' => 'Confirmed and completed sales invoices by date range.',
            'group' => 'Sales',
            'icon' => 'heroicon-o-shopping-bag',
        ],
        'purchases' => [
            'title' => 'Purchase Report',
            'description' => 'Received purchases by date range.',
            'group' => 'Purchasing',
            'icon' => 'heroicon-o-truck',
        ],
        'profit' => [
            'title' => 'Product Profit Report',
            'description' => 'Product-wise revenue, cost, and profit from sales.',
            'group' => 'Sales',
            'icon' => 'heroicon-o-chart-pie',
        ],
        'stock' => [
            'title' => 'Stock Report',
            'description' => 'Current product stock, reorder level, and pricing.',
            'group' => 'Inventory',
            'icon' => 'heroicon-o-cube',
        ],
        'low-stock' => [
            'title' => 'Low Stock Report',
            'description' => 'Products that are at or below reorder level.',
            'group' => 'Inventory',
            'icon' => 'heroicon-o-exclamation-triangle',
        ],
        'customer-dues' => [
            'title' => 'Customer Due Report',
            'description' => 'Customers with outstanding balances.',
            'group' => 'Sales',
            'icon' => 'heroicon-o-user-group',
        ],
        'supplier-dues' => [
            'title' => 'Supplier Due Report',
            'description' => 'Suppliers with outstanding payables.',
            'group' => 'Purchasing',
            'icon' => 'heroicon-o-building-storefront',
        ],
        'expenses' => [
            'title' => 'Expense Report',
            'description' => 'Expenses by category and account within the selected date range.',
            'group' => 'Accounts',
            'icon' => 'heroicon-o-receipt-percent',
        ],
        'ledger' => [
            'title' => 'Account Transaction Report',
            'description' => 'Account ledger inflows and outflows by date range.',
            'group' => 'Accounts',
            'icon' => 'heroicon-o-banknotes',
        ],
    ];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $cluster = ReportsCluster::class;

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Reports';

    protected string $view = 'filament.pages.reports';

    public static function canAccess(): bool
    {
        return Auth::user()?->canAccessReports() ?? false;
    }

    public string $dateFrom;

    public string $dateTo;

    public string $reportType = 'sales';

    public array $summary = [];

    public Collection $sales;

    public Collection $purchases;

    public Collection $profit;

    public Collection $stock;

    public Collection $lowStock;

    public Collection $customerDues;

    public Collection $supplierDues;

    public Collection $expenses;

    public Collection $ledger;

    public function mount(ReportService $reports): void
    {
        $this->dateFrom = request('date_from', now()->toDateString());
        $this->dateTo = request('date_to', now()->toDateString());
        $this->reportType = request('report_type', 'sales');

        if (! array_key_exists($this->reportType, self::REPORTS)) {
            $this->reportType = 'sales';
        }

        [$from, $to] = $reports->dateRange($this->dateFrom, $this->dateTo);

        $this->summary = $reports->dashboardSummary();
        $this->sales = collect();
        $this->purchases = collect();
        $this->profit = collect();
        $this->stock = collect();
        $this->lowStock = collect();
        $this->customerDues = collect();
        $this->supplierDues = collect();
        $this->expenses = collect();
        $this->ledger = collect();

        match ($this->reportType) {
            'sales' => $this->sales = $reports->sales($from, $to),
            'purchases' => $this->purchases = $reports->purchases($from, $to),
            'profit' => $this->profit = $reports->profit($from, $to),
            'stock' => $this->stock = $reports->stock(),
            'low-stock' => $this->lowStock = $reports->lowStock(),
            'customer-dues' => $this->customerDues = $reports->customerDues(),
            'supplier-dues' => $this->supplierDues = $reports->supplierDues(),
            'expenses' => $this->expenses = $reports->expenses($from, $to),
            'ledger' => $this->ledger = $reports->ledger($from, $to),
        };
    }

    public function reportOptions(): array
    {
        return collect(self::REPORTS)
            ->mapWithKeys(fn (array $report, string $type): array => [$type => $report['title']])
            ->all();
    }

    public function reportGroups(): array
    {
        return collect(self::REPORTS)
            ->groupBy('group', preserveKeys: true)
            ->map(fn (Collection $reports): array => $reports->keys()->all())
            ->all();
    }

    public function reportIcon(string $type): string
    {
        return self::REPORTS[$type]['icon'];
    }

    public function activeReportTitle(): string
    {
        return self::REPORTS[$this->reportType]['title'];
    }

    public function activeReportDescription(): string
    {
        return self::REPORTS[$this->reportType]['description'];
    }

    public function activeReportGroup(): string
    {
        return self::REPORTS[$this->reportType]['group'];
    }

    public function activeReportCount(): int
    {
        return match ($this->reportType) {
            'sales' => $this->sales->count(),
            'purchases' => $this->purchases->count(),
            'profit' => $this->profit->count(),
            'stock' => $this->stock->count(),
            'low-stock' => $this->lowStock->count(),
            'customer-dues' => $this->customerDues->count(),
            'supplier-dues' => $this->supplierDues->count(),
            'expenses' => $this->expenses->count(),
            'ledger' => $this->ledger->count(),
        };
    }

    public function purchaseCustomCostLabels(): array
    {
        return $this->purchases
            ->flatMap(fn ($purchase): array => collect($purchase->custom_costs ?? [])
                ->pluck('label')
                ->filter()
                ->all())
            ->unique()
            ->values()
            ->all();
    }

    public function exportUrl(string $type): string
    {
        return route('reports.export', [
            'type' => $type,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
        ]);
    }

    public function exportPdfUrl(string $type): string
    {
        return route('reports.export.pdf', [
            'type' => $type,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
        ]);
    }

    public function money(float|int|string|null $amount): string
    {
        return 'BDT '.number_format((float) $amount, 2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading($this->activeReportTitle())
            ->description($this->activeReportDescription())
            ->records(fn (): array => $this->reportTableRecords())
            ->columns($this->reportTableColumns())
            ->headerActions([
                Action::make('exportCsv')
                    ->label('CSV')
                    ->icon(Heroicon::ArrowDownTray)
                    ->color('gray')
                    ->url($this->exportUrl($this->reportType)),
                Action::make('exportPdf')
                    ->label('PDF')
                    ->icon(Heroicon::DocumentArrowDown)
                    ->url($this->exportPdfUrl($this->reportType)),
            ])
            ->emptyStateHeading($this->activeReportEmptyStateHeading())
            ->emptyStateDescription('Try a different report type or date range.')
            ->emptyStateIcon($this->reportIcon($this->reportType))
            ->paginated(false)
            ->striped();
    }

    /**
     * @return array<TextColumn>
     */
    private function reportTableColumns(): array
    {
        return match ($this->reportType) {
            'sales' => [
                TextColumn::make('date')->label('Date'),
                TextColumn::make('invoice')->label('Invoice')->weight('medium')->copyable(),
                TextColumn::make('customer')->label('Customer')->placeholder('Not set'),
                $this->moneyColumn('total', 'Total'),
                $this->moneyColumn('paid', 'Paid'),
                $this->moneyColumn('due', 'Due'),
                $this->statusColumn('status'),
            ],
            'purchases' => [
                TextColumn::make('date')->label('Date'),
                TextColumn::make('purchase')->label('Purchase')->weight('medium')->copyable(),
                TextColumn::make('supplier')->label('Supplier')->placeholder('Not set'),
                $this->moneyColumn('china_to_bd_costs', 'China to BD Costs'),
                ...collect($this->purchaseCustomCostLabels())
                    ->values()
                    ->map(fn (string $label, int $index): TextColumn => $this->moneyColumn("custom_cost_{$index}", $label))
                    ->all(),
                $this->moneyColumn('total', 'Total'),
                $this->moneyColumn('paid', 'Paid'),
                $this->moneyColumn('due', 'Due'),
                $this->statusColumn('status', 'info'),
            ],
            'profit' => [
                TextColumn::make('date')->label('Date'),
                TextColumn::make('invoice')->label('Invoice')->weight('medium')->copyable(),
                TextColumn::make('product')->label('Product')->wrap(),
                TextColumn::make('quantity')->label('Qty')->numeric()->alignEnd(),
                $this->moneyColumn('revenue', 'Revenue'),
                $this->moneyColumn('cost', 'Cost'),
                $this->moneyColumn('profit', 'Profit'),
            ],
            'stock', 'low-stock' => [
                TextColumn::make('sku')->label('SKU')->weight('medium')->copyable(),
                TextColumn::make('product')->label('Product')->wrap(),
                TextColumn::make('category')->label('Category')->placeholder('Not set'),
                TextColumn::make('stock')
                    ->label('Stock')
                    ->numeric()
                    ->alignEnd()
                    ->badge($this->reportType === 'low-stock')
                    ->color('warning'),
                TextColumn::make('reorder')->label('Reorder')->numeric()->alignEnd(),
                $this->moneyColumn('cost', 'Cost'),
                $this->moneyColumn('sale_price', 'Sale Price'),
            ],
            'customer-dues' => [
                TextColumn::make('customer')->label('Customer')->weight('medium'),
                TextColumn::make('phone')->label('Phone')->placeholder('Not set')->copyable(),
                TextColumn::make('email')->label('Email')->placeholder('Not set')->copyable(),
                $this->moneyColumn('due', 'Due'),
            ],
            'supplier-dues' => [
                TextColumn::make('supplier')->label('Supplier')->weight('medium'),
                TextColumn::make('phone')->label('Phone')->placeholder('Not set')->copyable(),
                TextColumn::make('company')->label('Company')->placeholder('Not set'),
                $this->moneyColumn('payable', 'Payable'),
            ],
            'expenses' => [
                TextColumn::make('date')->label('Date'),
                TextColumn::make('expense')->label('Expense')->weight('medium')->copyable(),
                TextColumn::make('category')->label('Category')->placeholder('Not set'),
                TextColumn::make('account')->label('Account')->placeholder('Not set'),
                $this->moneyColumn('amount', 'Amount'),
            ],
            'ledger' => [
                TextColumn::make('date')->label('Date'),
                TextColumn::make('account')->label('Account')->placeholder('Not set'),
                TextColumn::make('type')->label('Type'),
                TextColumn::make('direction')
                    ->label('Direction')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'out' ? 'danger' : 'success')
                    ->formatStateUsing(fn (string $state): string => str($state)->headline()),
                $this->moneyColumn('amount', 'Amount'),
                TextColumn::make('note')->label('Note')->placeholder('Not set')->wrap(),
            ],
        };
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function reportTableRecords(): array
    {
        return match ($this->reportType) {
            'sales' => $this->sales
                ->mapWithKeys(fn ($order): array => ["order-{$order->id}" => [
                    'date' => optional($order->order_date)->format('d M Y'),
                    'invoice' => $order->order_number,
                    'customer' => $order->customer?->name,
                    'total' => $order->total_amount,
                    'paid' => $order->paid_amount,
                    'due' => $order->due_amount,
                    'status' => $order->status,
                ]])
                ->all(),
            'purchases' => $this->purchases
                ->mapWithKeys(function ($purchase): array {
                    $customCosts = collect($this->purchaseCustomCostLabels())
                        ->values()
                        ->mapWithKeys(fn (string $label, int $index): array => [
                            "custom_cost_{$index}" => $purchase->customCostAmountFor($label),
                        ])
                        ->all();

                    return ["purchase-{$purchase->id}" => [
                        'date' => optional($purchase->purchase_date)->format('d M Y'),
                        'purchase' => $purchase->purchase_number,
                        'supplier' => $purchase->supplier?->name,
                        'china_to_bd_costs' => $purchase->chinaToBdCostTotal(),
                        ...$customCosts,
                        'total' => $purchase->total_amount,
                        'paid' => $purchase->paid_amount,
                        'due' => $purchase->due_amount,
                        'status' => $purchase->status,
                    ]];
                })
                ->all(),
            'profit' => $this->profit
                ->values()
                ->mapWithKeys(fn (array $row, int $index): array => ["profit-{$index}" => $row])
                ->all(),
            'stock', 'low-stock' => ($this->reportType === 'stock' ? $this->stock : $this->lowStock)
                ->mapWithKeys(fn ($product): array => ["product-{$product->id}" => [
                    'sku' => $product->sku,
                    'product' => $product->name,
                    'category' => $product->category?->name,
                    'stock' => $product->stock,
                    'reorder' => $product->reorder_level,
                    'cost' => $product->cost_price,
                    'sale_price' => $product->sale_price ?? $product->price,
                ]])
                ->all(),
            'customer-dues' => $this->customerDues
                ->mapWithKeys(fn ($customer): array => ["customer-{$customer->id}" => [
                    'customer' => $customer->name,
                    'phone' => $customer->phone,
                    'email' => $customer->email,
                    'due' => $customer->current_balance,
                ]])
                ->all(),
            'supplier-dues' => $this->supplierDues
                ->mapWithKeys(fn ($supplier): array => ["supplier-{$supplier->id}" => [
                    'supplier' => $supplier->name,
                    'phone' => $supplier->phone,
                    'company' => $supplier->company_name,
                    'payable' => $supplier->current_balance,
                ]])
                ->all(),
            'expenses' => $this->expenses
                ->mapWithKeys(fn ($expense): array => ["expense-{$expense->id}" => [
                    'date' => optional($expense->expense_date)->format('d M Y'),
                    'expense' => $expense->expense_number,
                    'category' => $expense->category?->name,
                    'account' => $expense->account?->name,
                    'amount' => $expense->amount,
                ]])
                ->all(),
            'ledger' => $this->ledger
                ->mapWithKeys(fn ($entry): array => ["ledger-{$entry->id}" => [
                    'date' => optional($entry->transaction_date)->format('d M Y'),
                    'account' => $entry->account?->name,
                    'type' => str($entry->type)->headline()->toString(),
                    'direction' => $entry->direction,
                    'amount' => $entry->amount,
                    'note' => $entry->note,
                ]])
                ->all(),
        };
    }

    private function moneyColumn(string $name, string $label): TextColumn
    {
        return TextColumn::make($name)
            ->label($label)
            ->formatStateUsing(fn (float|int|string|null $state): string => $this->money($state))
            ->alignEnd();
    }

    private function statusColumn(string $name, string $color = 'success'): TextColumn
    {
        return TextColumn::make($name)
            ->label('Status')
            ->badge()
            ->color($color)
            ->formatStateUsing(fn (string $state): string => str($state)->headline());
    }

    private function activeReportEmptyStateHeading(): string
    {
        return match ($this->reportType) {
            'sales' => 'No sales found',
            'purchases' => 'No purchases found',
            'profit' => 'No profit data found',
            'stock', 'low-stock' => 'No products found',
            'customer-dues' => 'No customer dues found',
            'supplier-dues' => 'No supplier payables found',
            'expenses' => 'No expenses found',
            'ledger' => 'No transactions found',
        };
    }
}
