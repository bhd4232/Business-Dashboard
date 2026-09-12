<x-filament-panels::page>
    @php($cards = $this->toolCards())

    @if ($cards === [])
        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                No AI tools are available for your role yet. Ask an administrator to grant an "AI Tools" permission.
            </p>
        </x-filament::section>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($cards as $card)
                <div
                    @class([
                        'fi-section relative flex flex-col gap-3 rounded-xl border bg-white p-6 shadow-sm transition dark:bg-gray-900',
                        'border-gray-200 hover:border-primary-500 hover:shadow-md dark:border-white/10 dark:hover:border-primary-500' => $card['available'],
                        'border-dashed border-gray-200 opacity-70 dark:border-white/10' => ! $card['available'],
                    ])
                >
                    <div class="flex items-start gap-4">
                        <div
                            @class([
                                'flex h-11 w-11 shrink-0 items-center justify-center rounded-lg',
                                'bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400' => $card['available'],
                                'bg-gray-100 text-gray-400 dark:bg-white/5 dark:text-gray-500' => ! $card['available'],
                            ])
                        >
                            <x-filament::icon :icon="$card['icon']" class="h-6 w-6" />
                        </div>

                        <div class="min-w-0 space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                                    {{ $card['label'] }}
                                </h3>

                                @unless ($card['available'])
                                    <x-filament::badge color="gray" size="sm">Coming soon</x-filament::badge>
                                @endunless
                            </div>

                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ $card['description'] }}
                            </p>
                        </div>
                    </div>

                    @if ($card['url'])
                        {{-- Stretched link: keeps the whole card clickable without nesting an <a> around block content. --}}
                        <a
                            href="{{ $card['url'] }}"
                            wire:navigate
                            class="absolute inset-0 rounded-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                            aria-label="Open {{ $card['label'] }}"
                        ></a>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
