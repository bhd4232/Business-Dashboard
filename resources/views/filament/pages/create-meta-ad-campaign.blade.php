<x-filament-panels::page>
    @if ($this->accounts()->isEmpty())
        <x-filament::section icon="heroicon-o-exclamation-triangle" icon-color="warning">
            <p class="text-sm">
                No Meta Ads accounts configured yet. Add one on
                <a href="{{ \App\Filament\Resources\MetaAdAccounts\MetaAdAccountResource::getUrl() }}" class="underline font-medium">
                    Ads &rarr; Ad Accounts
                </a>
                first.
            </p>
        </x-filament::section>
    @else
        @if ($this->proposal())
            <x-filament::section icon="heroicon-o-sparkles" icon-color="primary">
                <p class="text-sm">
                    Prefilled from an AI recommendation — review everything below before creating the campaign.
                </p>
            </x-filament::section>
        @endif

        {{ $this->form }}
    @endif
</x-filament-panels::page>
