@php
    $themeColor = $setting->theme_color ?: '#0F766E';
    $logoUrl = $setting->logo ? asset('storage/'.$setting->logo) : null;
    $title = $setting->meta_title ?: $company->name;
    $description = $setting->meta_description ?: 'Shop products from '.$company->name;
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
    <title>{{ $title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-stone-50 text-stone-950 antialiased dark:bg-stone-950 dark:text-stone-50" style="--storefront-brand: {{ $themeColor }};">
    <div class="bg-[var(--storefront-brand)] px-4 py-2 text-center text-xs font-black uppercase tracking-[0.28em] text-white">
        Official ERP-powered storefront - live stock, live catalog, direct ordering ready
    </div>

    <header class="sticky top-0 z-40 border-b border-stone-200/80 bg-white/90 shadow-sm backdrop-blur-xl dark:border-white/10 dark:bg-stone-950/85">
        <div class="mx-auto flex min-h-20 w-full max-w-7xl items-center justify-between gap-5 px-4 sm:px-6 lg:px-8">
            <a class="flex items-center gap-3" href="{{ $homeUrl }}">
                @if ($logoUrl)
                    <img class="h-11 w-11 rounded-2xl border border-stone-200 object-cover shadow-sm dark:border-white/10" src="{{ $logoUrl }}" alt="{{ $company->name }} logo">
                @else
                    <span class="grid h-11 w-11 place-items-center rounded-2xl bg-[var(--storefront-brand)] text-lg font-black text-white shadow-lg shadow-stone-900/10">
                        {{ mb_substr($company->name, 0, 1) }}
                    </span>
                @endif
                <span>
                    <span class="block text-lg font-black tracking-tight">{{ $company->name }}</span>
                    <span class="hidden text-xs font-bold uppercase tracking-[0.2em] text-stone-500 sm:block dark:text-stone-400">Curated Store</span>
                </span>
            </a>

            <nav class="flex items-center gap-2 sm:gap-4" aria-label="Storefront navigation">
                <a class="hidden rounded-full px-4 py-2 text-sm font-extrabold text-stone-600 transition hover:bg-stone-100 hover:text-stone-950 sm:inline-flex dark:text-stone-300 dark:hover:bg-white/10 dark:hover:text-white" href="{{ $productsUrl }}">Catalog</a>
                <a class="hidden rounded-full px-4 py-2 text-sm font-extrabold text-stone-600 transition hover:bg-stone-100 hover:text-stone-950 sm:inline-flex dark:text-stone-300 dark:hover:bg-white/10 dark:hover:text-white" href="{{ $homeUrl }}#collections">Collections</a>
                <a class="hidden rounded-full px-4 py-2 text-sm font-extrabold text-stone-600 transition hover:bg-stone-100 hover:text-stone-950 md:inline-flex dark:text-stone-300 dark:hover:bg-white/10 dark:hover:text-white" href="{{ $trackUrl }}">Track</a>
                <a class="inline-flex rounded-full border border-stone-200 bg-white px-4 py-2 text-sm font-black text-stone-950 shadow-sm transition hover:-translate-y-0.5 hover:border-[var(--storefront-brand)] dark:border-white/10 dark:bg-white/10 dark:text-white" href="{{ $accountOrdersUrl }}">Account</a>
                <a class="relative inline-flex items-center rounded-full border border-stone-200 bg-white px-4 py-2 text-sm font-black text-stone-950 shadow-sm transition hover:-translate-y-0.5 hover:border-[var(--storefront-brand)] dark:border-white/10 dark:bg-white/10 dark:text-white" href="{{ $cartUrl }}">
                    Cart
                    @if ($cartCount > 0)
                        <span class="ml-2 grid h-5 min-w-5 place-items-center rounded-full bg-[var(--storefront-brand)] px-1 text-xs font-black text-white">{{ $cartCount }}</span>
                    @endif
                </a>
                @if ($setting->whatsapp_number)
                    <a class="inline-flex items-center rounded-full bg-stone-950 px-4 py-2 text-sm font-black text-white shadow-lg shadow-stone-900/15 transition hover:-translate-y-0.5 hover:bg-[var(--storefront-brand)] dark:bg-white dark:text-stone-950" href="https://wa.me/{{ preg_replace('/\D+/', '', $setting->whatsapp_number) }}" target="_blank" rel="noopener">
                        WhatsApp
                    </a>
                @endif
            </nav>
        </div>
    </header>

    <main>
        @if (session('storefront_status'))
            <div class="mx-auto mt-5 w-full max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-bold text-emerald-800 dark:border-emerald-400/20 dark:bg-emerald-400/10 dark:text-emerald-200">
                    {{ session('storefront_status') }}
                </div>
            </div>
        @endif
        @yield('content')
    </main>

    <footer class="mt-20 border-t border-stone-200 bg-white dark:border-white/10 dark:bg-stone-950">
        <div class="mx-auto grid w-full max-w-7xl gap-8 px-4 py-10 sm:px-6 md:grid-cols-[1fr_auto] lg:px-8">
            <div>
                <div class="text-xl font-black tracking-tight">{{ $company->name }}</div>
                <p class="mt-2 max-w-xl text-sm leading-6 text-stone-500 dark:text-stone-400">
                    Browse curated products from a live ERP catalog. Inventory, pricing, and storefront publishing are managed from ZamZam ERP.
                </p>
                @if ($footerPages->isNotEmpty())
                    <nav class="mt-5 flex flex-wrap gap-3" aria-label="Storefront pages">
                        @foreach ($footerPages as $footerPage)
                            <a class="text-sm font-black text-stone-500 transition hover:text-[var(--storefront-brand)] dark:text-stone-400" href="{{ isset($previewSlug) ? route('storefront.preview.pages.show', [$previewSlug, $footerPage->slug]) : route('storefront.pages.show', $footerPage->slug) }}">
                                {{ $footerPage->title }}
                            </a>
                        @endforeach
                    </nav>
                @endif
            </div>
            <div class="text-sm font-bold text-stone-500 dark:text-stone-400">
                &copy; {{ now()->year }} {{ $company->name }}. Powered by ZamZam ERP.
            </div>
        </div>
    </footer>
</body>
</html>
