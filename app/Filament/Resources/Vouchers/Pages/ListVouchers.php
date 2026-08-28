<?php

namespace App\Filament\Resources\Vouchers\Pages;

use App\Filament\Resources\Vouchers\VoucherResource;
use App\Filament\Resources\Vouchers\Widgets\FundTransfersWidget;
use App\Filament\Resources\Vouchers\Widgets\VoucherSummaryWidget;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListVouchers extends ListRecords
{
    protected static string $resource = VoucherResource::class;

    /**
     * Deep-links this page's main table to one VoucherSummaryWidget card's
     * exact type/status combination, e.g. `?cardType=credit&cardStatuses=
     * pending,verified`. Deliberately not Filament's native `tableFilters`
     * query string - see VoucherSummaryWidget's docblock for why.
     */
    #[Url]
    public ?string $cardType = null;

    #[Url]
    public ?string $cardStatuses = null;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            VoucherSummaryWidget::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            FundTransfersWidget::class,
        ];
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return 1;
    }

    protected function getTableQuery(): Builder
    {
        /** @var Builder $query */
        $query = parent::getTableQuery();

        return $query
            ->when($this->cardType, fn (Builder $q, string $type): Builder => $q->where('type', $type))
            ->when($this->cardStatuses, fn (Builder $q, string $statuses): Builder => $q->whereIn('status', explode(',', $statuses)));
    }
}
