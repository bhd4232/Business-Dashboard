<x-filament-panels::page>
    @if (! $this->hasSelectedCompany())
        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Select a single company from the switcher above to use Image Generation.
            </p>
        </x-filament::section>
    @else
        <x-filament::section
            icon="heroicon-o-sparkles"
            heading="New image"
            description="Describe what you want. Generation runs in the background — results appear below."
        >
            <form wire:submit="generate" id="image-generation-form" class="space-y-6">
                {{ $this->form }}
            </form>

            @php($remaining = $this->remainingMonthlyAllowance())
            @if (! is_null($remaining))
                <p class="mt-4 text-xs {{ $remaining === 0 ? 'text-danger-600 dark:text-danger-400' : 'text-gray-500 dark:text-gray-400' }}">
                    Monthly allowance: <span class="font-medium">{{ $remaining }}</span> {{ \Illuminate\Support\Str::plural('image', $remaining) }} left this month.
                </p>
            @endif
        </x-filament::section>

        <div
            class="space-y-4"
            @if ($this->hasPendingGenerations()) wire:poll.5s @endif
        >
            <h3 class="text-base font-semibold text-gray-950 dark:text-white">Your recent generations</h3>

            @forelse ($this->recentGenerations() as $generation)
                <x-filament::section>
                    <div class="flex flex-col gap-3">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="min-w-0 space-y-1">
                                <p class="text-sm text-gray-950 dark:text-white">{{ \Illuminate\Support\Str::limit($generation->prompt, 160) }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $generation->contextLabel() }}
                                    &middot; {{ $generation->aspect_ratio }}
                                    &middot; {{ $generation->provider_label ?: $generation->api_format }}
                                    &middot; {{ $generation->created_at?->diffForHumans() }}
                                </p>
                            </div>

                            @php($status = $generation->status)
                            <x-filament::badge :color="match ($status) {
                                \App\Models\GeneratedImage::STATUS_COMPLETED => 'success',
                                \App\Models\GeneratedImage::STATUS_FAILED => 'danger',
                                default => 'warning',
                            }">
                                {{ \Illuminate\Support\Str::headline($status) }}
                            </x-filament::badge>
                        </div>

                        @if ($status === \App\Models\GeneratedImage::STATUS_FAILED)
                            <p class="text-sm text-danger-600 dark:text-danger-400">
                                {{ $generation->error_message ?: 'The image could not be generated.' }}
                            </p>
                        @elseif ($status !== \App\Models\GeneratedImage::STATUS_COMPLETED)
                            <p class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                                <x-filament::loading-indicator class="h-4 w-4" />
                                Generating {{ $generation->variations_requested > 1 ? $generation->variations_requested.' images' : 'your image' }}…
                            </p>
                        @else
                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                                @foreach ($generation->outputUrls() as $index => $url)
                                    <div class="flex flex-col gap-1.5">
                                        <div class="group relative overflow-hidden rounded-lg border border-gray-200 dark:border-white/10">
                                            <a href="{{ $url }}" target="_blank" rel="noopener">
                                                <img src="{{ $url }}" alt="" class="aspect-square w-full object-cover" loading="lazy" />
                                            </a>
                                            <a
                                                href="{{ $url }}"
                                                download
                                                class="absolute bottom-1 right-1 rounded-md bg-gray-950/70 px-2 py-1 text-xs font-medium text-white opacity-0 transition group-hover:opacity-100"
                                            >
                                                Download
                                            </a>
                                        </div>
                                        <div class="flex flex-wrap gap-1">
                                            <x-filament::button
                                                size="xs"
                                                color="gray"
                                                icon="heroicon-o-shopping-bag"
                                                wire:click="mountAction('attachToProduct', { generation: {{ $generation->id }}, image: {{ $index }} })"
                                            >
                                                Product
                                            </x-filament::button>
                                            <x-filament::button
                                                size="xs"
                                                color="gray"
                                                icon="heroicon-o-rectangle-group"
                                                wire:click="mountAction('attachToOffer', { generation: {{ $generation->id }}, image: {{ $index }} })"
                                            >
                                                Offer banner
                                            </x-filament::button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            @if (in_array($generation->review_status, [\App\Models\GeneratedImage::REVIEW_PENDING, \App\Models\GeneratedImage::REVIEW_REJECTED], true))
                                <x-filament::badge :color="$generation->review_status === \App\Models\GeneratedImage::REVIEW_PENDING ? 'warning' : 'danger'" icon="heroicon-o-shield-check">
                                    {{ $generation->reviewStatusLabel() }}{{ $generation->review_status === \App\Models\GeneratedImage::REVIEW_PENDING ? ' — cannot be attached yet' : '' }}
                                </x-filament::badge>
                            @endif

                            <div class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 pt-3 dark:border-white/5">
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    @if ($generation->linkedLabel())
                                        Attached to <span class="font-medium text-gray-700 dark:text-gray-300">{{ $generation->linkedLabel() }}</span>
                                    @else
                                        Not attached to a record yet.
                                    @endif
                                </p>

                                <div class="flex flex-wrap items-center gap-2">
                                    <x-filament::button
                                        size="xs"
                                        :color="$generation->is_favorite ? 'warning' : 'gray'"
                                        :icon="$generation->is_favorite ? 'heroicon-s-star' : 'heroicon-o-star'"
                                        wire:click="mountAction('toggleFavorite', { generation: {{ $generation->id }} })"
                                    >
                                        {{ $generation->is_favorite ? 'Favourited' : 'Favourite' }}
                                    </x-filament::button>

                                    @if ($generation->is_video_reference)
                                        <x-filament::badge color="info" icon="heroicon-o-film">Video reference</x-filament::badge>
                                    @else
                                        <x-filament::button
                                            size="xs"
                                            color="gray"
                                            icon="heroicon-o-film"
                                            wire:click="mountAction('useAsVideoReference', { generation: {{ $generation->id }} })"
                                        >
                                            Mark as video reference
                                        </x-filament::button>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                </x-filament::section>
            @empty
                <x-filament::section>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        No generations yet. Fill in a prompt above and press Generate.
                    </p>
                </x-filament::section>
            @endforelse
        </div>
    @endif
</x-filament-panels::page>
