<x-filament-panels::page>
    @if ($this->hasSelectedCompany())
        <form id="company-settings-form" wire:submit="save">
            {{ $this->form }}
        </form>
    @else
        <x-filament::empty-state
            heading="Select a company to edit settings"
            description="Company Settings is company-specific. Choose a company from the top-bar company switcher to load its profile, branding, shipping, and invoice settings."
            icon="heroicon-o-building-office-2"
        />
    @endif
</x-filament-panels::page>
