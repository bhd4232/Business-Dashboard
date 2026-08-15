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
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                {{ $this->form }}
            </div>
        </div>

        {{ $this->table }}
    @endif
</x-filament-panels::page>
