{{--
    Byte-identical to vendor filament-widgets::stats-overview-widget (so the
    grid/columns/spacing stay pixel-identical to every other StatsOverviewWidget
    in the app) plus the action-modals renderer that BusinessOverview's
    click-to-drilldown Stat cards need. See App\Filament\Widgets\BusinessOverview.
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
