@if (! empty($data['items']))
    <section class="border-b border-gray-200 py-10 dark:border-white/10">
        <div class="mx-auto w-full max-w-4xl px-4 sm:px-5 lg:px-6">
            @if (! empty($data['heading']))
                <h2 class="text-center text-2xl font-semibold tracking-tight text-gray-950 dark:text-white">{{ $data['heading'] }}</h2>
            @endif
            <ul class="mt-6 grid gap-4 sm:grid-cols-2">
                @foreach ($data['items'] as $point)
                    @continue(empty($point['text']))
                    <li class="flex items-start gap-3 rounded-lg border border-gray-200 bg-white p-4 text-sm dark:border-white/10 dark:bg-gray-900">
                        <svg class="mt-0.5 h-5 w-5 shrink-0 text-[var(--storefront-brand)]" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                        <span class="text-gray-700 dark:text-gray-200">{{ $point['text'] }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>
@endif
