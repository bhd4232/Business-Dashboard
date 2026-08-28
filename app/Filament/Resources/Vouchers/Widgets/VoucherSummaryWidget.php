<?php

namespace App\Filament\Resources\Vouchers\Widgets;

use App\Filament\Resources\Vouchers\VoucherResource;
use App\Models\FundTransfer;
use App\Models\Voucher;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Collection;

/**
 * Dashboard-style summary cards at the top of the Vouchers page: one card
 * per (Credit Voucher | Debit Voucher | Fund Transfer) x (Requested |
 * Approved | Rejected) combination, each showing that combination's live
 * count. "Requested" groups Voucher's `pending` + `verified` statuses
 * together (both are pre-final-decision states in the approval workflow -
 * Credit vouchers normally pass through `verified` before `approved`, Debit
 * vouchers can go straight from `pending` to `approved` - see
 * VoucherWorkflowTest), matching the 3 buckets the owner asked for rather
 * than Voucher::STATUSES' full 5. `cancelled` is deliberately not its own
 * card, same reasoning.
 *
 * Each card links back to this same Vouchers page pre-filtered to that
 * exact combination. This does NOT use Filament's native `tableFilters`
 * query-string hydration - CourierMerchantDashboard's docblock records that
 * being confirmed broken on a cold GET in this Filament install. Instead
 * this reuses the plain-query-string + `#[Url]`-bound-property pattern
 * ProductStatsOverview's "Total Shortage" card already ships with
 * (BulkUpdateStock's `$shortageOnly`): ListVouchers reads `cardType`/
 * `cardStatuses`, FundTransfersWidget reads `ftStatus`, both applied as a
 * plain `->where()`/`->whereIn()` on the query, not through Filament's
 * filter UI state at all.
 */
class VoucherSummaryWidget extends StatsOverviewWidget
{
    // Filament widgets lazy-load (AJAX, after the page's first paint) by
    // default - fine for a normal browser visit, but these cards are the
    // whole point of the page, so render them synchronously on first load
    // instead, matching FundTransfersWidget/ProductStatsOverview's own
    // `$isLazy = false` on the same page/pattern.
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
        $voucherCounts = Voucher::query()
            ->selectRaw('type, status, count(*) as aggregate')
            ->groupBy('type', 'status')
            ->get()
            ->groupBy('type')
            ->map(fn (Collection $rows): Collection => $rows->pluck('aggregate', 'status'));

        $fundTransferCounts = FundTransfer::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $stats = [];

        foreach (Voucher::TYPES as $type => $label) {
            $byStatus = $voucherCounts->get($type, collect());
            $requested = (int) $byStatus->get(Voucher::STATUS_PENDING, 0) + (int) $byStatus->get(Voucher::STATUS_VERIFIED, 0);
            $approved = (int) $byStatus->get(Voucher::STATUS_APPROVED, 0);
            $rejected = (int) $byStatus->get(Voucher::STATUS_REJECTED, 0);

            $icon = $type === Voucher::TYPE_CREDIT ? Heroicon::OutlinedDocumentPlus : Heroicon::OutlinedDocumentMinus;

            $stats[] = Stat::make("{$label} - Requested", $requested)
                ->icon($icon)
                ->color('warning')
                ->url($this->voucherUrl($type, [Voucher::STATUS_PENDING, Voucher::STATUS_VERIFIED]));
            $stats[] = Stat::make("{$label} - Approved", $approved)
                ->icon($icon)
                ->color('success')
                ->url($this->voucherUrl($type, [Voucher::STATUS_APPROVED]));
            $stats[] = Stat::make("{$label} - Rejected", $rejected)
                ->icon($icon)
                ->color('danger')
                ->url($this->voucherUrl($type, [Voucher::STATUS_REJECTED]));
        }

        $stats[] = Stat::make('Fund Transfer - Requested', (int) $fundTransferCounts->get(FundTransfer::STATUS_PENDING, 0))
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->color('warning')
            ->url($this->fundTransferUrl(FundTransfer::STATUS_PENDING));
        $stats[] = Stat::make('Fund Transfer - Approved', (int) $fundTransferCounts->get(FundTransfer::STATUS_APPROVED, 0))
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->color('success')
            ->url($this->fundTransferUrl(FundTransfer::STATUS_APPROVED));
        $stats[] = Stat::make('Fund Transfer - Rejected', (int) $fundTransferCounts->get(FundTransfer::STATUS_REJECTED, 0))
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->color('danger')
            ->url($this->fundTransferUrl(FundTransfer::STATUS_REJECTED));

        // Owner: these 3-part labels ("Credit Voucher - Requested") wrap to
        // 3 lines at Filament's default stat-card text size, making the
        // cards look awkwardly tall on mobile — same compacting technique
        // BusinessOverview already uses for the main Dashboard's own cards
        // (see .zz-voucher-summary-stat in theme.css).
        foreach ($stats as $stat) {
            $stat->extraAttributes(['class' => 'zz-voucher-summary-stat'], merge: true);
        }

        return $stats;
    }

    /**
     * @param  array<int, string>  $statuses
     */
    protected function voucherUrl(string $type, array $statuses): string
    {
        return VoucherResource::getUrl('index').'?'.http_build_query([
            'cardType' => $type,
            'cardStatuses' => implode(',', $statuses),
        ]);
    }

    protected function fundTransferUrl(string $status): string
    {
        return VoucherResource::getUrl('index').'?'.http_build_query(['ftStatus' => $status]);
    }
}
