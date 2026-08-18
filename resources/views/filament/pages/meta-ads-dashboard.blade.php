<x-filament-panels::page>
    @php
        $account = $this->selectedAccount();
        $campaign = $this->selectedCampaign();
        $adSet = $this->selectedAdSet();
    @endphp

    @if ($this->accounts()->isEmpty())
        <x-filament::section icon="heroicon-o-exclamation-triangle" icon-color="warning">
            <p class="text-sm">
                No Meta Ads accounts configured yet. Add one on
                <a href="{{ \App\Filament\Resources\MetaAdAccounts\MetaAdAccountResource::getUrl() }}" class="underline font-medium">
                    Ads &rarr; Ad Accounts
                </a>
                to get started.
            </p>
        </x-filament::section>
    @else
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                {{ $this->form }}
            </div>
        </div>

        @if ($campaign || $adSet)
            <nav class="flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400">
                <button type="button" wire:click="drillBackToCampaigns" class="underline hover:text-primary-600 dark:hover:text-primary-400">
                    Campaigns
                </button>
                @if ($campaign)
                    <span>/</span>
                    @if ($adSet)
                        <button type="button" wire:click="drillBackToAdSets" class="underline hover:text-primary-600 dark:hover:text-primary-400">
                            {{ $campaign->name }}
                        </button>
                    @else
                        <span class="font-medium text-gray-700 dark:text-gray-200">{{ $campaign->name }}</span>
                    @endif
                @endif
                @if ($adSet)
                    <span>/</span>
                    <span class="font-medium text-gray-700 dark:text-gray-200">{{ $adSet->name }}</span>
                @endif
            </nav>
        @endif

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
            @foreach ($this->stats() as $stat)
                <div class="fi-wi-stats-overview-stat !p-[10px]">
                    <div class="fi-wi-stats-overview-stat-content">
                        <div class="fi-wi-stats-overview-stat-label-ctn">
                            <x-filament::icon :icon="$stat['icon']" class="h-5 w-5 shrink-0" />
                            <span class="fi-wi-stats-overview-stat-label">{{ $stat['label'] }}</span>
                        </div>
                        <div class="fi-wi-stats-overview-stat-value !text-[20px]">
                            @if ($stat['isCurrency'] ?? false)
                                {{ $account?->currency ?? 'USD' }} {{ \App\Support\MoneyFormatter::number($stat['value']) }}
                            @elseif ($stat['isPercent'] ?? false)
                                {{ \App\Support\MoneyFormatter::number($stat['value']) }}%
                            @else
                                {{ number_format($stat['value']) }}
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{ $this->table }}
    @endif
</x-filament-panels::page>
