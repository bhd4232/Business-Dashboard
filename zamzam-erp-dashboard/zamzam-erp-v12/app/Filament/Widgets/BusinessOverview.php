<?php

namespace App\Filament\Widgets;

use App\Services\ReportService;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BusinessOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'Business Overview';

    protected ?string $description = 'Today and current balance summary';

    protected function getStats(): array
    {
        $summary = app(ReportService::class)->dashboardSummary();

        return [
            Stat::make('Today Sales', $this->money($summary['sales_today']))
                ->icon(Heroicon::OutlinedDocumentCurrencyBangladeshi)
                ->color('success'),
            Stat::make('Today Purchases', $this->money($summary['purchases_today']))
                ->icon(Heroicon::OutlinedShoppingBag)
                ->color('warning'),
            Stat::make('Customer Payments', $this->money($summary['customer_payments_today']))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('success'),
            Stat::make('Supplier Payments', $this->money($summary['supplier_payments_today']))
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->color('danger'),
            Stat::make('Today Expenses', $this->money($summary['expenses_today']))
                ->icon(Heroicon::OutlinedReceiptPercent)
                ->color('danger'),
            Stat::make('Customer Due', $this->money($summary['customer_due']))
                ->icon(Heroicon::OutlinedUserGroup)
                ->color('warning'),
            Stat::make('Supplier Payable', $this->money($summary['supplier_due']))
                ->icon(Heroicon::OutlinedBuildingStorefront)
                ->color('warning'),
            Stat::make('Account Balance', $this->money($summary['account_balance']))
                ->icon(Heroicon::OutlinedWallet)
                ->color('success'),
            Stat::make('Low Stock Items', $summary['low_stock_count'])
                ->icon(Heroicon::OutlinedArchiveBox)
                ->color($summary['low_stock_count'] > 0 ? 'danger' : 'success'),
            Stat::make('Coming Soon Products', $summary['coming_soon_count'])
                ->icon(Heroicon::OutlinedClock)
                ->color($summary['coming_soon_count'] > 0 ? 'warning' : 'success'),
        ];
    }

    protected function money(float|int|string $amount): string
    {
        return 'BDT ' . number_format((float) $amount, 2);
    }
}
