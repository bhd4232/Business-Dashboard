<x-filament-panels::page>
    @if ($this->hasSelectedCompany())
        <form wire:submit="save" class="space-y-6">
            {{ $this->form }}

            <x-filament::button type="submit" icon="heroicon-m-check" wire:loading.attr="disabled">
                Save company settings
            </x-filament::button>
        </form>
    @else
        <x-filament::empty-state
            heading="Select a company to edit settings"
            description="Company Settings is company-specific. Choose a company from the top-bar company switcher to load its profile, branding, shipping, and invoice settings."
            icon="heroicon-o-building-office-2"
        >
            <x-slot name="footer">
                <x-filament::button
                    tag="a"
                    :href="route('filament.admin.company-management.resources.companies.index')"
                    color="gray"
                    icon="heroicon-m-building-storefront"
                >
                    View companies
                </x-filament::button>
            </x-slot>
        </x-filament::empty-state>
    @endif
</x-filament-panels::page>
