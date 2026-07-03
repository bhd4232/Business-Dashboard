@php
    $themeColor = $setting->theme_color ?: '#0F766E';
    $themeMode = $setting->theme_mode ?: 'system';
    $logoUrl = $setting->logo ? asset('storage/'.$setting->logo) : null;
    $title = $setting->meta_title ?: $company->name;
    $description = $setting->meta_description ?: 'Shop products from '.$company->name;
    $bannerImage = collect($setting->banner_images ?? [])->filter()->first();
    $shareImageUrl = $bannerImage ? asset('storage/'.$bannerImage) : ($logoUrl ?: null);
    $homeUrl = isset($previewSlug) ? route('storefront.preview.show', $previewSlug) : route('marketing.home');
    $productsUrl = isset($previewSlug) ? route('storefront.preview.products.index', $previewSlug) : route('storefront.products.index');
    $cartUrl = isset($previewSlug) ? route('storefront.preview.cart.show', $previewSlug) : route('storefront.cart.show');
    $trackUrl = isset($previewSlug) ? route('storefront.preview.track.index', $previewSlug) : route('storefront.track.index');
    $accountOrdersUrl = isset($previewSlug) ? route('storefront.preview.account.orders', $previewSlug) : route('storefront.account.orders');
    $cartCount = app(\App\Services\StorefrontCart::class)->count($company);
    $footerPages = \Illuminate\Support\Facades\Schema::hasTable('storefront_pages')
        ? \App\Models\StorefrontPage::query()
            ->where('company_id', $company->getKey())
            ->published()
            ->orderBy('sort_order')
            ->orderBy('title')
            ->limit(6)
            ->get()
        : collect();
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $description }}">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:type" content="website">
    @if ($shareImageUrl)
        <meta property="og:image" content="{{ $shareImageUrl }}">
    @endif
    <meta name="twitter:card" content="{{ $shareImageUrl ? 'summary_large_image' : 'summary' }}">
    <title>{{ $title }}</title>
    <script>
        (function () {
            var stored = null;
            try { stored = localStorage.getItem('storefrontTheme'); } catch (e) {}
            var mode = stored ?? '{{ $themeMode }}';
            var dark = mode === 'dark' || (mode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', dark);
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body
    class="bg-white text-gray-900 antialiased dark:bg-gray-950 dark:text-gray-100"
    style="--storefront-brand: {{ $themeColor }};"
>
    <div class="border-b border-gray-200 bg-gray-50 px-4 py-2 text-center text-xs font-medium tracking-wide text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-400">
        Official storefront - live catalog, direct ordering
    </div>

    <header class="sticky top-0 z-40 border-b border-gray-200 bg-white/95 backdrop-blur dark:border-white/10 dark:bg-gray-950/95">
        <div class="mx-auto flex min-h-[72px] w-full max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
            <a class="flex min-w-0 items-center gap-3" href="{{ $homeUrl }}">
                @if ($logoUrl)
                    <img class="h-10 w-10 shrink-0 rounded-lg border border-gray-200 object-cover dark:border-white/10" src="{{ $logoUrl }}" alt="{{ $company->name }} logo">
                @else
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-[var(--storefront-brand)] text-base font-semibold text-white">
                        {{ mb_substr($company->name, 0, 1) }}
                    </span>
                @endif
                <span class="min-w-0 truncate text-lg font-semibold tracking-tight">{{ $company->name }}</span>
            </a>

            <nav class="hidden items-center gap-8 text-sm font-medium text-gray-600 sm:flex dark:text-gray-300" aria-label="Storefront navigation">
                <a class="border-b-2 border-transparent pb-1 transition hover:border-[var(--storefront-brand)] hover:text-gray-950 dark:hover:text-white" href="{{ $productsUrl }}">Shop all</a>
                <a class="border-b-2 border-transparent pb-1 transition hover:border-[var(--storefront-brand)] hover:text-gray-950 dark:hover:text-white" href="{{ $homeUrl }}#collections">Collections</a>
                <a class="border-b-2 border-transparent pb-1 transition hover:border-[var(--storefront-brand)] hover:text-gray-950 dark:hover:text-white" href="{{ $trackUrl }}">Track order</a>
                <a class="border-b-2 border-transparent pb-1 transition hover:border-[var(--storefront-brand)] hover:text-gray-950 dark:hover:text-white" href="{{ $accountOrdersUrl }}">Account</a>
            </nav>

            <div class="flex shrink-0 items-center gap-2">
                <button
                    type="button"
                    data-theme-toggle
                    class="grid h-10 w-10 place-items-center rounded-lg border border-gray-200 text-gray-600 transition hover:border-gray-300 hover:text-gray-950 dark:border-white/10 dark:text-gray-300 dark:hover:text-white"
                    aria-label="Toggle light and dark mode"
                >
                    <svg data-theme-icon-light xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2m0 14v2m9-9h-2M5 12H3m15.36 6.36-1.42-1.42M7.05 7.05 5.64 5.64m12.72 0-1.42 1.42M7.05 16.95l-1.42 1.42M16 12a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z"/></svg>
                    <svg data-theme-icon-dark class="hidden h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z"/></svg>
                </button>
                @if ($setting->whatsapp_number)
                    <a class="hidden h-10 items-center rounded-lg bg-gray-950 px-4 text-sm font-medium text-white transition hover:bg-[var(--storefront-brand)] md:inline-flex dark:bg-white dark:text-gray-950" href="https://wa.me/{{ preg_replace('/\D+/', '', $setting->whatsapp_number) }}" target="_blank" rel="noopener">
                        WhatsApp
                    </a>
                @endif
                <a class="relative inline-flex h-10 items-center whitespace-nowrap rounded-lg border border-gray-200 px-4 text-sm font-medium text-gray-900 transition hover:border-gray-300 dark:border-white/10 dark:text-white" href="{{ $cartUrl }}">
                    Cart
                    @if ($cartCount > 0)
                        <span class="ml-2 grid h-5 min-w-5 place-items-center rounded-full bg-[var(--storefront-brand)] px-1 text-xs font-semibold text-white">{{ $cartCount }}</span>
                    @endif
                </a>
            </div>
        </div>
    </header>

    <main>
        @if (session('storefront_status'))
            <div class="mx-auto mt-5 w-full max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-medium text-emerald-800 dark:border-emerald-400/20 dark:bg-emerald-400/10 dark:text-emerald-200">
                    {{ session('storefront_status') }}
                </div>
            </div>
        @endif
        @yield('content')
    </main>

    <footer class="mt-20 border-t border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-white/[0.02]">
        <div class="mx-auto grid w-full max-w-7xl gap-10 px-4 py-12 sm:px-6 md:grid-cols-3 lg:px-8">
            <div>
                <div class="text-lg font-semibold tracking-tight">{{ $company->name }}</div>
                <p class="mt-3 max-w-sm text-sm leading-6 text-gray-500 dark:text-gray-400">
                    Browse curated products, place direct orders, and track storefront purchases from {{ $company->name }}.
                </p>
            </div>
            @if ($footerPages->isNotEmpty())
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Pages</div>
                    <nav class="mt-3 flex flex-col gap-2" aria-label="Storefront pages">
                        @foreach ($footerPages as $footerPage)
                            <a class="text-sm text-gray-600 transition hover:text-[var(--storefront-brand)] dark:text-gray-400" href="{{ isset($previewSlug) ? route('storefront.preview.pages.show', [$previewSlug, $footerPage->slug]) : route('storefront.pages.show', $footerPage->slug) }}">
                                {{ $footerPage->title }}
                            </a>
                        @endforeach
                    </nav>
                </div>
            @endif
            <div>
                <div class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Contact</div>
                <div class="mt-3 flex flex-col items-start gap-3">
                    @if ($setting->whatsapp_number)
                        <a class="inline-flex rounded-lg bg-[var(--storefront-brand)] px-4 py-2 text-sm font-medium text-white" href="https://wa.me/{{ preg_replace('/\D+/', '', $setting->whatsapp_number) }}" target="_blank" rel="noopener">
                            Chat on WhatsApp
                        </a>
                    @endif
                    <div class="text-sm text-gray-500 dark:text-gray-400">&copy; {{ now()->year }} {{ $company->name }}.</div>
                </div>
            </div>
        </div>
    </footer>

    <script>
        (function () {
            var toggle = document.querySelector('[data-theme-toggle]');
            if (! toggle) { return; }

            var syncIcons = function () {
                var dark = document.documentElement.classList.contains('dark');
                document.querySelectorAll('[data-theme-icon-light]').forEach(function (el) { el.classList.toggle('hidden', dark); });
                document.querySelectorAll('[data-theme-icon-dark]').forEach(function (el) { el.classList.toggle('hidden', ! dark); });
            };

            toggle.addEventListener('click', function () {
                var dark = ! document.documentElement.classList.contains('dark');
                document.documentElement.classList.toggle('dark', dark);
                try { localStorage.setItem('storefrontTheme', dark ? 'dark' : 'light'); } catch (e) {}
                syncIcons();
            });

            syncIcons();
        })();

        (function () {
            document.addEventListener('click', function (event) {
                var button = event.target.closest('[data-qty-decrement], [data-qty-increment]');
                if (! button) { return; }

                var wrapper = button.closest('[data-qty-stepper]');
                var input = wrapper && wrapper.querySelector('[data-qty-input]');
                if (! input) { return; }

                var step = button.hasAttribute('data-qty-decrement') ? -1 : 1;
                var min = parseInt(input.min || '0', 10);
                var max = input.max ? parseInt(input.max, 10) : Infinity;
                var next = (parseInt(input.value || '0', 10) || 0) + step;

                input.value = Math.min(max, Math.max(min, next));
            });
        })();
    </script>
</body>
</html>
