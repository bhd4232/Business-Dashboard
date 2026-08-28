<?php

namespace App\Filament\Resources\Accounts\Widgets;

use App\Filament\Resources\Accounts\AccountResource;
use App\Models\Account;
use App\Support\MoneyFormatter;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Dashboard-style summary cards at the top of the Accounts page: one card
 * per account (every account that exists, not just active ones - the owner
 * asked for however many are "created"), showing its live current balance.
 * Clicking a card opens that exact account's own page - a real per-record
 * route (AccountResource's `view`), not a filtered-table deep link, so this
 * needs none of VoucherSummaryWidget's query-string-filter workaround.
 */
class AccountSummaryWidget extends StatsOverviewWidget
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
        return Account::query()
            ->orderBy('name')
            ->get()
            ->map(function (Account $account): Stat {
                $balance = $account->balance();

                return Stat::make($account->name, MoneyFormatter::currency($balance))
                    ->icon(Heroicon::OutlinedWallet)
                    ->color($balance < 0 ? 'danger' : 'success')
                    ->url(AccountResource::getUrl('view', ['record' => $account]))
                    // Owner: compact these cards the same way as Vouchers'
                    // (see .zz-account-summary-stat in theme.css) — long
                    // account names wrapped and made the cards look tall.
                    ->extraAttributes(['class' => 'zz-account-summary-stat'], merge: true);
            })
            ->all();
    }
}
