<x-filament-panels::page>
    @if ($this->hasSelectedCompany())
        <form id="integrations-form" wire:submit="save">
            {{ $this->form }}
        </form>

        <div class="mt-8">
            <h2 class="text-base font-semibold mb-3">Multi-provider integrations</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">
                Courier Providers and Meta Ads each manage many providers/ad accounts of their own, so they keep
                their full dedicated pages — this just shows whether at least one is connected.
            </p>
            @livewire(\App\Filament\Pages\IntegrationWidgets\IntegrationStatusWidget::class)
        </div>
    @else
        <x-filament::empty-state
            heading="Select a company to see its integration settings"
            description="Integrations are configured per company. Choose a company from the top-bar company switcher to load its settings."
            icon="heroicon-o-building-office-2"
        />
    @endif
</x-filament-panels::page>
