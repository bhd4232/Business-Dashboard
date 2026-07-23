<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <div class="flex flex-wrap items-center gap-3">
            <x-filament::button type="submit" icon="heroicon-m-check" wire:loading.attr="disabled">
                Save settings
            </x-filament::button>

            <x-filament::button
                type="button"
                color="gray"
                icon="heroicon-m-signal"
                wire:click="testPublicConnection"
                wire:loading.attr="disabled"
                tooltip="Saves the current draft, then tests it without enabling R2."
            >
                Test public bucket
            </x-filament::button>

            <x-filament::button
                type="button"
                color="gray"
                icon="heroicon-m-lock-closed"
                wire:click="testPrivateConnection"
                wire:loading.attr="disabled"
                tooltip="Saves the current draft, then tests it without changing the R2 enable switch."
            >
                Test private bucket
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
