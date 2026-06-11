<?php

namespace App\Filament\Pages;

use App\Services\ReportService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class Reports extends Page
{
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

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

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

    public function money(float|int|string|null $amount): string
    {
        return 'BDT ' . number_format((float) $amount, 2);
    }
}
