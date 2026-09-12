<x-filament-panels::page>
    @if (! $this->hasSelectedCompany())
        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Select a single company from the switcher above to manage its Image Generation governance.
            </p>
        </x-filament::section>
    @else
        <form wire:submit="save" class="space-y-6">
            {{ $this->form }}
        </form>

        @php($usage = $this->usage())

        <x-filament::section
            icon="heroicon-o-banknotes"
            heading="This month's usage"
            description="Calendar month to date. Estimated cost uses each provider profile's admin-set approximate cost per image."
        >
            <div class="grid grid-cols-2 gap-4 sm:max-w-md">
                <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Images generated</p>
                    <p class="text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format($usage['images']) }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Estimated spend</p>
                    <p class="text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format($usage['cost'], 2) }}</p>
                </div>
            </div>

            <div class="mt-6 grid gap-6 lg:grid-cols-2">
                <div>
                    <h4 class="mb-2 text-sm font-semibold text-gray-950 dark:text-white">By person</h4>
                    @if (empty($usage['by_user']))
                        <p class="text-sm text-gray-500 dark:text-gray-400">No generations yet this month.</p>
                    @else
                        <table class="w-full text-sm">
                            <thead class="text-xs text-gray-500 dark:text-gray-400">
                                <tr><th class="py-1 text-left font-medium">Person</th><th class="py-1 text-right font-medium">Images</th><th class="py-1 text-right font-medium">Est. cost</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($usage['by_user'] as $row)
                                    <tr class="border-t border-gray-100 dark:border-white/5">
                                        <td class="py-1.5">{{ $row['name'] }}</td>
                                        <td class="py-1.5 text-right">{{ number_format($row['images']) }}</td>
                                        <td class="py-1.5 text-right">{{ number_format($row['cost'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>

                <div>
                    <h4 class="mb-2 text-sm font-semibold text-gray-950 dark:text-white">By provider</h4>
                    @if (empty($usage['by_provider']))
                        <p class="text-sm text-gray-500 dark:text-gray-400">No generations yet this month.</p>
                    @else
                        <table class="w-full text-sm">
                            <thead class="text-xs text-gray-500 dark:text-gray-400">
                                <tr><th class="py-1 text-left font-medium">Provider</th><th class="py-1 text-right font-medium">Images</th><th class="py-1 text-right font-medium">Est. cost</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($usage['by_provider'] as $row)
                                    <tr class="border-t border-gray-100 dark:border-white/5">
                                        <td class="py-1.5">{{ $row['label'] }}</td>
                                        <td class="py-1.5 text-right">{{ number_format($row['images']) }}</td>
                                        <td class="py-1.5 text-right">{{ number_format($row['cost'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
