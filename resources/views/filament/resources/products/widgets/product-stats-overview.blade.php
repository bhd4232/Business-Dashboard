{{--
    Reuses Filament's own native stat-card CSS classes (fi-wi-stats-overview-
    stat*, shipped in vendor/filament/filament/dist/theme.css and loaded on
    every panel page) on hand-built markup, same as the Courier Merchant
    Dashboard's status cards — so this renders pixel-consistent with every
    other stat card in the app. Hand-built rather than a plain
    StatsOverviewWidget so the grid can be an exact 5-columns-desktop/
    2-columns-mobile layout with responsive typography and card padding
    (owner-specified: mobile 12px label / 16px value, desktop 20px value,
    and 10px card padding).
--}}
<x-filament-widgets::widget>
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
        @foreach ($this->stats() as $stat)
            @php $cardTag = ($stat['url'] ?? null) ? 'a' : 'div'; @endphp
            <{{ $cardTag }}
                @if ($stat['url'] ?? null) href="{{ $stat['url'] }}" @endif
                class="fi-wi-stats-overview-stat !p-[10px] {{ ($stat['url'] ?? null) ? 'transition hover:!bg-gray-50 dark:hover:!bg-white/5' : '' }}"
            >
                <div class="fi-wi-stats-overview-stat-content">
                    <div class="fi-wi-stats-overview-stat-label-ctn">
                        <x-filament::icon :icon="$stat['icon']" class="h-5 w-5 shrink-0" />
                        <span class="fi-wi-stats-overview-stat-label !text-xs lg:!text-sm">{{ $stat['label'] }}</span>
                    </div>

                    <div class="fi-wi-stats-overview-stat-value !text-base lg:!text-[20px] {{ $stat['valueColor'] }}">
                        @if ($stat['isCurrency'] ?? false)
                            {{ $this->formatCurrency($stat['value']) }}
                        @else
                            {{ number_format($stat['value']) }}
                        @endif
                    </div>
                </div>
            </{{ $cardTag }}>
        @endforeach
    </div>
</x-filament-widgets::widget>
