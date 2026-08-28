{{--
    Byte-identical to vendor filament-widgets::stats-overview-widget (so the
    grid/columns/spacing stay pixel-identical to every other StatsOverviewWidget
    in the app) plus the action-modals renderer that click-to-drilldown Stat
    cards need. Shared by every widget with that UX, not just
    BusinessOverview: also VoucherSummaryWidget, AccountSummaryWidget, and
    ExpenseCategorySummaryWidget (see App\Filament\Concerns\HasDrilldownStatCards).
--}}
@php
    $pollingInterval = $this->getPollingInterval();
@endphp

<x-filament-widgets::widget
    :attributes="
        (new \Filament\Support\View\ComponentAttributeBag)
            ->merge([
                'wire:poll.' . $pollingInterval => $pollingInterval ? true : null,
            ], escape: false)
            ->class([
                'fi-wi-stats-overview',
            ])
    "
>
    {{ $this->content }}

    <x-filament-actions::modals />
</x-filament-widgets::widget>
