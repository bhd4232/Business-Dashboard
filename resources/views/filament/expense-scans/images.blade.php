@php
    /** @var \App\Models\ExpenseScan|null $scan */
    $paths = $scan?->imagePaths() ?? [];
@endphp

<div class="space-y-3">
    @if ($scan?->isBusy())
        <div wire:poll.5s="refreshScan" class="flex items-center gap-2 text-sm font-medium text-primary-600 dark:text-primary-400">
            <x-filament::loading-indicator class="h-5 w-5" />
            {{ __('The AI is reading your expenses — the lines below will appear in a moment.') }}
        </div>
    @endif

    @if (filled($scan?->source_text))
        <div>
            <p class="mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Pasted text') }}</p>
            <pre class="max-h-[32rem] overflow-auto whitespace-pre-wrap rounded-lg border border-gray-200 bg-gray-50 p-3 font-sans text-sm text-gray-800 dark:border-white/10 dark:bg-white/5 dark:text-gray-200">{{ $scan->source_text }}</pre>
        </div>
    @endif

    @if ($paths === [])
        @if (blank($scan?->source_text))
            <p class="text-sm text-gray-500 dark:text-gray-400">No photo.</p>
        @endif
    @else
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            @foreach ($paths as $index => $path)
                @php($url = route('expense-scans.image', ['scan' => $scan->getKey(), 'index' => $index]))
                <a href="{{ $url }}" target="_blank" rel="noopener" class="block overflow-hidden rounded-lg border border-gray-200 dark:border-white/10">
                    <img src="{{ $url }}" alt="Expense photo {{ $index + 1 }}" loading="lazy" class="max-h-[32rem] w-full object-contain bg-gray-50 dark:bg-white/5">
                </a>
            @endforeach
        </div>
        <p class="text-xs text-gray-500 dark:text-gray-400">Tap a photo to open it full size.</p>
    @endif
</div>
