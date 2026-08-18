@if (! empty($data['steps']))
    <section class="border-b border-gray-200 bg-gray-50 py-10 dark:border-white/10 dark:bg-white/5">
        <div class="mx-auto w-full max-w-4xl px-4 sm:px-5 lg:px-6">
            @if (! empty($data['heading']))
                <h2 class="text-center text-2xl font-semibold tracking-tight text-gray-950 dark:text-white">{{ $data['heading'] }}</h2>
            @endif
            <ol class="mt-6 grid gap-4 sm:grid-cols-3">
                @foreach ($data['steps'] as $index => $step)
                    @continue(blank($step))
                    <li class="rounded-lg border border-gray-200 bg-white p-4 text-sm dark:border-white/10 dark:bg-gray-900">
                        <span class="grid h-8 w-8 place-items-center rounded-full bg-[var(--storefront-brand)] text-sm font-semibold text-white">{{ $index + 1 }}</span>
                        <p class="mt-3 text-gray-700 dark:text-gray-200">{{ $step }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>
@endif
