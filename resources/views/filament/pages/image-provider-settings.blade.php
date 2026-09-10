<x-filament-panels::page>
    @if (! $this->hasSelectedCompany())
        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Select a single company from the switcher above to configure its image providers.
            </p>
        </x-filament::section>
    @else
        <x-filament::section
            icon="heroicon-o-key"
            heading="Image providers"
            description="Add one or more providers, each with its own model and API key. Staff pick which one to use in the Image Generation tool. Keys are stored encrypted per company."
        >
            <form wire:submit="save" class="space-y-6">
                {{ $this->form }}
            </form>
        </x-filament::section>
    @endif
</x-filament-panels::page>
