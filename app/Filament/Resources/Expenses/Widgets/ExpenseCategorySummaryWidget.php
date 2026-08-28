<?php

namespace App\Filament\Resources\Expenses\Widgets;

use App\Filament\Resources\ExpenseCategories\ExpenseCategoryResource;
use App\Models\ExpenseCategory;
use App\Support\MoneyFormatter;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Dashboard-style summary cards at the top of the Expenses page: one card
 * per expense category (every category that exists, same "show them all"
 * rule as AccountSummaryWidget), showing the total amount spent in that
 * category to date. Clicking a card opens that exact category's own page
 * (ExpenseCategoryResource's `view`) - a real per-record route, matching
 * AccountSummaryWidget's card-links-to-its-own-page behavior exactly.
 */
class ExpenseCategorySummaryWidget extends StatsOverviewWidget
{
    // See VoucherSummaryWidget's docblock: these cards are the point of the
    // page, so they render synchronously instead of lazy/AJAX-loading.
    protected static bool $isLazy = false;

    /**
     * @return array<string, int>
     */
    protected function getColumns(): array
    {
        return [
            'default' => 2,
            'lg' => 5,
        ];
    }

    protected function getStats(): array
    {
        return ExpenseCategory::query()
            ->withSum('expenses', 'amount')
            ->orderBy('name')
            ->get()
            ->map(fn (ExpenseCategory $category): Stat => Stat::make($category->name, MoneyFormatter::currency($category->expenses_sum_amount ?? 0))
                ->icon(Heroicon::OutlinedReceiptPercent)
                ->color('danger')
                ->url(ExpenseCategoryResource::getUrl('view', ['record' => $category]))
                // Owner: compact these cards the same way as Vouchers' (see
                // .zz-expense-summary-stat in theme.css) — long category
                // names wrapped and made the cards look tall.
                ->extraAttributes(['class' => 'zz-expense-summary-stat'], merge: true))
            ->all();
    }
}
