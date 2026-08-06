@php
    $themePalette = $setting->themePalette();
    $themeColor = $themePalette['primary'];
    $themeForeground = $themePalette['primary_contrast'];
    $themeMode = $setting->theme_mode ?: 'system';
    $googleFontsUrl = $setting->googleFontsUrl();
    $cornerRadii = $setting->appearanceCornerRadii();
    $logoUrl = \App\Support\CompanyMedia::publicUrl($setting->logo, $company);
    $logoDarkUrl = \App\Support\CompanyMedia::publicUrl($setting->logo_dark, $company);
    $title = $setting->meta_title ?: $company->name;
    $description = $setting->meta_description ?: 'Shop products from '.$company->name;
    $bannerImage = \App\Models\StorefrontSlide::forCompany($company->getKey())->first()?->image;
    $shareImageUrl = \App\Support\CompanyMedia::publicUrl($bannerImage, $company) ?: ($logoUrl ?: null);
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
    $navCategories = \App\Models\Category::query()
        ->where('company_id', $company->getKey())
        ->where('is_active', true)
        ->whereHas('products', fn ($query) => $query->where('is_active', true)->where('status', \App\Models\Product::STATUS_AVAILABLE))
        ->orderBy('name')
        ->limit(10)
        ->get();
    $categoryUrl = fn (\App\Models\Category $category) => isset($previewSlug)
        ? route('storefront.preview.categories.show', [$previewSlug, $category->slug])
        : route('storefront.categories.show', $category->slug);
    $previewSlug = $previewSlug ?? null; // isset() stays false when null
    $resellerUrl = isset($previewSlug) ? route('storefront.preview.reseller.show', $previewSlug) : route('storefront.reseller.show');
    $pageUrl = fn (string $slug) => isset($previewSlug)
        ? route('storefront.preview.pages.show', [$previewSlug, $slug])
        : route('storefront.pages.show', $slug);

    $accountsEnabled = (bool) ($setting->customer_accounts_enabled ?? true);
    $authCustomer = $accountsEnabled ? \Illuminate\Support\Facades\Auth::guard('customer')->user() : null;
    $accountRoute = static fn (string $name) => isset($previewSlug)
        ? route('storefront.preview.account.'.$name, $previewSlug)
        : route('storefront.account.'.$name);
    $loginUrl = $accountsEnabled ? $accountRoute('login') : null;
    $registerUrl = $accountsEnabled ? $accountRoute('register') : null;
    $accountUrl = $accountsEnabled ? $accountRoute('index') : null;
    $profileUrl = $accountsEnabled ? $accountRoute('profile') : null;
    $activityUrl = $accountsEnabled ? $accountRoute('activity') : null;
    $logoutUrl = $accountsEnabled ? $accountRoute('logout') : null;
    $mobileAccountUrl = $accountsEnabled ? ($authCustomer ? $accountUrl : $loginUrl) : $trackUrl;
    $isCurrentUrl = static function (?string $candidate): bool {
        if (blank($candidate)) {
            return false;
        }

        $candidateHost = parse_url($candidate, PHP_URL_HOST);
        if ($candidateHost && strcasecmp($candidateHost, request()->getHost()) !== 0) {
            return false;
        }

        $candidatePath = parse_url($candidate, PHP_URL_PATH) ?: '/';
        $currentPath = '/'.ltrim(request()->path(), '/');

        return rtrim($candidatePath, '/') === rtrim($currentPath, '/');
    };
    $isHomeCurrent = request()->routeIs('marketing.home', 'storefront.preview.show');
    $isCatalogCurrent = request()->routeIs(
        'storefront.products.*',
        'storefront.categories.*',
        'storefront.preview.products.*',
        'storefront.preview.categories.*',
    );
    $isCartCurrent = request()->routeIs(
        'storefront.cart.*',
        'storefront.checkout.*',
        'storefront.preview.cart.*',
        'storefront.preview.checkout.*',
    );
    $isAccountCurrent = $accountsEnabled
        ? request()->routeIs('storefront.account.*')
        : request()->routeIs('storefront.track.*', 'storefront.preview.track.*');

    // Admin-managed navigation menus (Storefront Settings → Navigation Menus).
    $menuCategorySlugs = collect([$setting->header_menu, $setting->footer_menu])->flatten(1)->filter()->where('type', 'category')->pluck('category_id')->filter()->unique();
    $menuPageSlugs = collect([$setting->header_menu, $setting->footer_menu])->flatten(1)->filter()->where('type', 'page')->pluck('page_id')->filter()->unique();
    $menuCategorySlugs = $menuCategorySlugs->isEmpty() ? collect() : \App\Models\Category::withoutGlobalScopes()->where('company_id', $company->getKey())->whereIn('id', $menuCategorySlugs)->where('is_active', true)->pluck('slug', 'id');
    $menuPageSlugs = $menuPageSlugs->isEmpty() ? collect() : \App\Models\StorefrontPage::withoutGlobalScopes()->where('company_id', $company->getKey())->whereIn('id', $menuPageSlugs)->where('is_published', true)->pluck('slug', 'id');
    $resolveMenu = function (?array $items) use ($productsUrl, $trackUrl, $accountOrdersUrl, $resellerUrl, $previewSlug, $menuCategorySlugs, $menuPageSlugs, $pageUrl) {
        return collect($items ?? [])->map(function ($item) use ($productsUrl, $trackUrl, $accountOrdersUrl, $resellerUrl, $previewSlug, $menuCategorySlugs, $menuPageSlugs, $pageUrl) {
            $label = trim((string) ($item['label'] ?? ''));
            $url = match ($item['type'] ?? null) {
                'shop' => $productsUrl,
                'track' => $trackUrl,
                'account' => $accountOrdersUrl,
                'reseller' => $resellerUrl,
                'category' => ($slug = $menuCategorySlugs->get($item['category_id'] ?? null))
                    ? (isset($previewSlug) ? route('storefront.preview.categories.show', [$previewSlug, $slug]) : route('storefront.categories.show', $slug))
                    : null,
                'page' => ($slug = $menuPageSlugs->get($item['page_id'] ?? null)) ? $pageUrl($slug) : null,
                'custom' => filled($item['url'] ?? null) ? $item['url'] : null,
                default => null,
            };

            return ($label === '' || $url === null) ? null : ['label' => $label, 'url' => $url, 'new_tab' => (bool) ($item['new_tab'] ?? false)];
        })->filter()->values();
    };
    $headerMenu = $resolveMenu($setting->header_menu);
    $footerMenu = $resolveMenu($setting->footer_menu);
    $sisterCompanies = \App\Models\Company::query()
        ->where('id', '!=', $company->getKey())
        ->where('is_active', true)
        ->whereNotNull('domain')
        ->whereHas('storefrontSetting', fn ($query) => $query->where('is_published', true))
        ->orderBy('name')
        ->get();
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
    <meta name="theme-color" content="{{ $themePalette['background'] }}" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="{{ $themePalette['dark_background'] }}" media="(prefers-color-scheme: dark)">
    @php
        $publicDisk = app(\App\Services\CompanyStorageService::class)->publicDiskName();
        $storageHost = parse_url(\Illuminate\Support\Facades\Storage::disk($publicDisk)->url(''), PHP_URL_HOST);
    @endphp
    @if ($storageHost && $storageHost !== request()->getHost())
        <link rel="preconnect" href="https://{{ $storageHost }}">
    @endif
    @if ($googleFontsUrl)
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="stylesheet" href="{{ $googleFontsUrl }}">
    @endif
    <title>{{ $title }}</title>
    <script>
        (function () {
            var stored = null;
            try { stored = localStorage.getItem('storefrontTheme'); } catch (e) {}
            var mode = stored ?? '{{ $themeMode }}';
            var dark = mode === 'dark' || (mode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', dark);
            document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body
    class="storefront-shell bg-white text-gray-900 antialiased dark:bg-gray-950 dark:text-gray-100"
    data-storefront-theme="{{ $setting->storefrontTheme() }}"
    data-homepage-template="{{ $setting->homepageTemplate() }}"
    data-typography-scale="{{ $setting->typographyScale() }}"
    data-card-hover="{{ $setting->appearanceCardHover() }}"
    data-motion="{{ $setting->appearance_motion ?: 'standard' }}"
    style="--storefront-brand: {{ $themeColor }};
        --storefront-brand-contrast: {{ $themeForeground }};
        --storefront-secondary: {{ $themePalette['secondary'] }};
        --storefront-secondary-contrast: {{ $themePalette['secondary_contrast'] }};
        --storefront-accent: {{ $themePalette['accent'] }};
        --storefront-accent-contrast: {{ $themePalette['accent_contrast'] }};
        --storefront-background: {{ $themePalette['background'] }};
        --storefront-surface: {{ $themePalette['surface'] }};
        --storefront-text: {{ $themePalette['text'] }};
        --storefront-muted-text: {{ $themePalette['muted_text'] }};
        --storefront-border: {{ $themePalette['border'] }};
        --storefront-dark-background: {{ $themePalette['dark_background'] }};
        --storefront-dark-surface: {{ $themePalette['dark_surface'] }};
        --storefront-dark-text: {{ $themePalette['dark_text'] }};
        --storefront-dark-muted-text: {{ $themePalette['dark_muted_text'] }};
        --storefront-dark-border: {{ $themePalette['dark_border'] }};
        --storefront-heading-font: {{ $setting->headingFontFamily() }};
        --storefront-body-font: {{ $setting->bodyFontFamily() }};
        --storefront-base-size: {{ $setting->typographyBaseSize() }}px;
        --storefront-heading-weight: {{ $setting->typographyHeadingWeight() }};
        --storefront-body-weight: {{ $setting->typographyBodyWeight() }};
        --storefront-line-height: {{ $setting->typographyLineHeight() }};
        --storefront-heading-tracking: {{ $setting->typographyHeadingTracking() }};
        --storefront-body-tracking: {{ $setting->typographyBodyTracking() }};
        --storefront-content-measure: {{ $setting->typographyContentWidth() }};
        --storefront-content-width: {{ $setting->appearanceContainerWidth() }};
        --storefront-page-gutter: {{ $setting->appearancePageGutter() }};
        --storefront-card-shadow: {{ $setting->appearanceCardShadow() }};
        --storefront-card-shadow-dark: {{ $setting->appearanceCardShadow(true) }};
        --storefront-motion-duration: {{ $setting->appearanceMotionDuration() }};
        --radius-sm: {{ $cornerRadii['sm'] }};
        --radius-md: {{ $cornerRadii['md'] }};
        --radius-lg: {{ $cornerRadii['lg'] }};
        --radius-xl: {{ $cornerRadii['xl'] }};
        --radius-2xl: {{ $cornerRadii['2xl'] }};"
>
    <a class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-[var(--storefront-brand)] focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-[var(--storefront-brand-contrast)]" href="#main-content">
        Skip to content
    </a>

    <header class="storefront-header sticky top-0 z-40 border-b border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950">
        <div class="mx-auto flex min-h-[60px] w-full max-w-7xl items-center justify-between gap-3 px-4 sm:px-5 lg:px-6">
            <a class="flex min-h-10 min-w-0 items-center gap-2.5" href="{{ $homeUrl }}">
                @if ($logoUrl)
                    <img class="h-9 w-auto max-w-[200px] shrink-0 object-contain sm:max-w-[240px] {{ $logoDarkUrl ? 'dark:hidden' : '' }}" src="{{ $logoUrl }}" alt="{{ $company->name }} logo" width="800" height="281">
                    @if ($logoDarkUrl)
                        <img class="hidden h-9 w-auto max-w-[200px] shrink-0 object-contain sm:max-w-[240px] dark:block" src="{{ $logoDarkUrl }}" alt="{{ $company->name }} logo" width="800" height="281">
                    @endif
                    <span class="sr-only">{{ $company->name }}</span>
                @else
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-md bg-[var(--storefront-brand)] text-sm font-semibold text-white">
                        {{ mb_substr($company->name, 0, 1) }}
                    </span>
                    <span class="min-w-0 truncate text-base font-bold">{{ $company->name }}</span>
                @endif
            </a>

            <form class="mx-3 hidden min-w-0 max-w-3xl flex-1 md:flex lg:mx-6" role="search" method="GET" action="{{ $productsUrl }}">
                <label class="sr-only" for="storefront-search-desktop">Search products</label>
                <div class="relative w-full">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.34-4.34M19 11a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z"/></svg>
                    <input
                        id="storefront-search-desktop"
                        type="search"
                        name="q"
                        value="{{ $search ?? request('q') }}"
                        placeholder="Search products…"
                        autocomplete="off"
                        enterkeyhint="search"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-gray-50 py-2 pl-9 pr-4 text-sm outline-none transition focus:border-[var(--storefront-brand)] focus:bg-white focus:ring-1 focus:ring-[var(--storefront-brand)] dark:border-gray-700 dark:bg-gray-900 dark:text-white dark:focus:bg-gray-950"
                    >
                </div>
            </form>

            <div class="flex shrink-0 items-center gap-2">
                <button
                    type="button"
                    data-mobile-menu-toggle
                    data-open-label="Open menu"
                    data-close-label="Close menu"
                    class="grid h-10 w-10 place-items-center rounded-md border border-gray-300 text-gray-600 transition hover:border-gray-400 hover:bg-gray-100 hover:text-gray-950 lg:hidden dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white"
                    aria-label="Open menu"
                    aria-expanded="false"
                    aria-controls="storefront-mobile-menu"
                >
                    <svg data-mobile-menu-icon-open class="h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5"/></svg>
                    <svg data-mobile-menu-icon-close class="hidden h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                </button>
                @if ($setting->phone_number)
                    <a class="hidden h-10 w-10 items-center justify-center rounded-md border border-gray-300 text-gray-600 transition hover:border-gray-400 hover:text-gray-950 lg:inline-flex dark:border-gray-700 dark:text-gray-300 dark:hover:text-white" href="tel:{{ preg_replace('/\s+/', '', $setting->phone_number) }}" title="Call support" aria-label="Call support">
                        <svg class="h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h1.5a2.25 2.25 0 0 0 2.25-2.25v-1.372a1 1 0 0 0-.804-.98l-4.204-.841a1 1 0 0 0-1.028.417l-.92 1.38a1 1 0 0 1-1.21.38 12.035 12.035 0 0 1-5.512-5.512 1 1 0 0 1 .38-1.21l1.38-.92a1 1 0 0 0 .417-1.028l-.84-4.204a1 1 0 0 0-.98-.804H4.5a2.25 2.25 0 0 0-2.25 2.25v.75Z"/></svg>
                    </a>
                @endif
                @if ($setting->whatsapp_number)
                    <a class="hidden h-10 items-center rounded-md bg-[var(--storefront-brand)] px-4 text-sm font-semibold text-white transition hover:opacity-90 lg:inline-flex" href="https://wa.me/{{ preg_replace('/\D+/', '', $setting->whatsapp_number) }}" target="_blank" rel="noopener">
                        WhatsApp
                    </a>
                @endif

                <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
                    <button
                        type="button"
                        @click="open = ! open"
                        :aria-expanded="open.toString()"
                        aria-controls="storefront-account-menu"
                        class="flex h-10 min-w-10 items-center justify-center gap-1 rounded-md border border-gray-300 px-2 text-gray-600 transition hover:border-gray-400 hover:bg-gray-100 hover:text-gray-950 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white"
                        aria-label="{{ $authCustomer ? 'Account menu' : 'Account' }}"
                    >
                        @if ($authCustomer)
                            <span class="grid h-6 w-6 place-items-center rounded-full bg-[var(--storefront-brand)] text-[11px] font-semibold text-white" aria-hidden="true">
                                {{ mb_strtoupper(mb_substr($authCustomer->name, 0, 1)) }}
                            </span>
                        @else
                            <svg class="h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                        @endif
                        <svg class="hidden h-3.5 w-3.5 transition sm:block" :class="open ? 'rotate-180' : ''" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div class="absolute right-0 top-full z-30 w-72 max-w-[calc(100vw-2rem)] pt-1.5" id="storefront-account-menu" x-show="open" x-cloak x-transition>
                        <div class="rounded-lg border border-gray-200 bg-white p-1.5 shadow-xl dark:border-gray-800 dark:bg-gray-950" role="menu">
                            @if (! $accountsEnabled)
                                <a class="block rounded-lg px-3 py-2 text-sm text-gray-700 transition hover:bg-gray-100 hover:text-gray-950 dark:text-gray-300 dark:hover:bg-white/10 dark:hover:text-white" href="{{ $trackUrl }}" @click="open = false">Track an order</a>
                            @elseif ($authCustomer)
                                <div class="flex items-center gap-2.5 border-b border-gray-100 px-2 py-2 dark:border-gray-800">
                                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-[var(--storefront-brand)] text-sm font-semibold text-white" aria-hidden="true">{{ mb_strtoupper(mb_substr($authCustomer->name, 0, 1)) }}</span>
                                    <div class="min-w-0">
                                        <div class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $authCustomer->name }}</div>
                                        <div class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $authCustomer->phone }}</div>
                                    </div>
                                </div>
                                <div class="px-2.5 pb-0.5 pt-1.5 text-[10px] font-semibold uppercase tracking-wider text-gray-400">My account</div>
                                <a class="flex min-h-10 items-center gap-2.5 rounded-lg px-2.5 text-sm font-medium transition {{ request()->routeIs('storefront.account.index', 'storefront.preview.account.index') ? 'bg-gray-100 text-gray-950 dark:bg-gray-800 dark:text-white' : 'text-gray-700 hover:bg-gray-100 hover:text-gray-950 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white' }}" href="{{ $accountUrl }}" role="menuitem" @click="open = false">
                                    @include('storefront.account.partials.nav-icon', ['icon' => 'home'])
                                    Account overview
                                </a>
                                <a class="flex min-h-10 items-center gap-2.5 rounded-lg px-2.5 text-sm font-medium transition {{ request()->routeIs('storefront.account.profile', 'storefront.preview.account.profile') ? 'bg-gray-100 text-gray-950 dark:bg-gray-800 dark:text-white' : 'text-gray-700 hover:bg-gray-100 hover:text-gray-950 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white' }}" href="{{ $profileUrl }}" role="menuitem" @click="open = false">
                                    @include('storefront.account.partials.nav-icon', ['icon' => 'user'])
                                    Profile settings
                                </a>
                                <a class="flex min-h-10 items-center gap-2.5 rounded-lg px-2.5 text-sm font-medium transition {{ request()->routeIs('storefront.account.orders', 'storefront.preview.account.orders') ? 'bg-gray-100 text-gray-950 dark:bg-gray-800 dark:text-white' : 'text-gray-700 hover:bg-gray-100 hover:text-gray-950 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white' }}" href="{{ $accountOrdersUrl }}" role="menuitem" @click="open = false">
                                    @include('storefront.account.partials.nav-icon', ['icon' => 'bag'])
                                    My orders
                                </a>
                                <a class="flex min-h-10 items-center gap-2.5 rounded-lg px-2.5 text-sm font-medium transition {{ request()->routeIs('storefront.account.activity', 'storefront.preview.account.activity') ? 'bg-gray-100 text-gray-950 dark:bg-gray-800 dark:text-white' : 'text-gray-700 hover:bg-gray-100 hover:text-gray-950 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white' }}" href="{{ $activityUrl }}" role="menuitem" @click="open = false">
                                    @include('storefront.account.partials.nav-icon', ['icon' => 'activity'])
                                    Account activity
                                </a>
                                <div class="my-0.5 border-t border-gray-100 dark:border-gray-800"></div>
                                <a class="flex min-h-10 items-center gap-2.5 rounded-lg px-2.5 text-sm text-gray-700 transition hover:bg-gray-100 hover:text-gray-950 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white" href="{{ $trackUrl }}" role="menuitem" @click="open = false">
                                    <svg class="h-5 w-5 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12a9 9 0 1 0 3-6.708M3 4.5v5.25h5.25M12 7.5V12l3 1.5"/></svg>
                                    Track an order
                                </a>
                                <a class="flex min-h-10 items-center gap-2.5 rounded-lg px-2.5 text-sm text-gray-700 transition hover:bg-gray-100 hover:text-gray-950 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white" href="{{ $resellerUrl }}" role="menuitem" @click="open = false">
                                    <svg class="h-5 w-5 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372A9.337 9.337 0 0 0 21 18.872M15 19.128v-.003c0-1.113-.285-2.16-.786-3.071M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766v-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/></svg>
                                    {{ in_array($authCustomer->reseller_status, [null, 'none'], true) ? 'Become a reseller' : 'Reseller status' }}
                                </a>
                                <form class="border-t border-gray-100 pt-0.5 dark:border-gray-800" method="POST" action="{{ $logoutUrl }}">
                                    @csrf
                                    <button class="flex min-h-10 w-full items-center gap-2.5 rounded-lg px-2.5 text-left text-sm font-medium text-red-600 transition hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/30" type="submit" role="menuitem">
                                        <svg class="h-5 w-5 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6A2.25 2.25 0 0 0 5.25 5.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12"/></svg>
                                        Log out
                                    </button>
                                </form>
                            @else
                                <a class="block rounded-lg px-3 py-2 text-sm font-medium text-gray-900 transition hover:bg-gray-100 dark:text-white dark:hover:bg-white/10" href="{{ $loginUrl }}" @click="open = false">Log in</a>
                                <a class="block rounded-lg px-3 py-2 text-sm text-gray-700 transition hover:bg-gray-100 hover:text-gray-950 dark:text-gray-300 dark:hover:bg-white/10 dark:hover:text-white" href="{{ $registerUrl }}" @click="open = false">Create account</a>
                                <a class="block rounded-lg px-3 py-2 text-sm text-gray-700 transition hover:bg-gray-100 hover:text-gray-950 dark:text-gray-300 dark:hover:bg-white/10 dark:hover:text-white" href="{{ $trackUrl }}" @click="open = false">Track an order</a>
                            @endif
                            <div class="my-0.5 border-t border-gray-100 dark:border-white/10"></div>
                            <button
                                type="button"
                                data-theme-toggle
                                data-light-label="Switch to light mode"
                                data-dark-label="Switch to dark mode"
                                class="flex min-h-10 w-full items-center gap-2.5 rounded-lg px-2.5 text-left text-sm text-gray-700 transition hover:bg-gray-100 hover:text-gray-950 dark:text-gray-300 dark:hover:bg-white/10 dark:hover:text-white"
                                aria-label="Switch to dark mode"
                                aria-pressed="false"
                            >
                                <span class="grid h-7 w-7 shrink-0 place-items-center rounded-md bg-gray-100 dark:bg-white/10">
                                    <svg data-theme-icon-light xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2m0 14v2m9-9h-2M5 12H3m15.36 6.36-1.42-1.42M7.05 7.05 5.64 5.64m12.72 0-1.42 1.42M7.05 16.95l-1.42 1.42M16 12a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z"/></svg>
                                    <svg data-theme-icon-dark class="hidden h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z"/></svg>
                                </span>
                                <span data-theme-toggle-label>Dark mode</span>
                            </button>
                        </div>
                    </div>
                </div>

                <a class="relative hidden h-10 items-center whitespace-nowrap rounded-md border border-gray-300 px-4 text-sm font-semibold text-gray-900 transition hover:border-[var(--storefront-brand)] sm:inline-flex dark:border-gray-700 dark:text-white" href="{{ $cartUrl }}">
                    Cart
                    @if ($cartCount > 0)
                        <span class="ml-2 grid h-5 min-w-5 place-items-center rounded-full bg-[var(--storefront-brand)] px-1 text-xs font-semibold text-white">{{ $cartCount }}</span>
                    @endif
                </a>
            </div>
        </div>

        <div class="hidden border-t border-gray-200 bg-gray-50 lg:block dark:border-gray-800 dark:bg-gray-900">
            <nav class="mx-auto flex min-h-10 w-full max-w-7xl flex-wrap items-center gap-x-6 gap-y-1 px-5 text-sm font-semibold text-gray-700 lg:px-6 dark:text-gray-200" aria-label="Storefront navigation">
                @if ($headerMenu->isNotEmpty())
                    @foreach ($headerMenu as $menuItem)
                        <a
                            class="inline-flex min-h-10 items-center border-b-2 transition hover:border-[var(--storefront-brand)] hover:text-gray-950 dark:hover:text-white {{ $isCurrentUrl($menuItem['url']) ? 'border-[var(--storefront-brand)] text-gray-950 dark:text-white' : 'border-transparent' }}"
                            href="{{ $menuItem['url'] }}"
                            @if ($isCurrentUrl($menuItem['url'])) aria-current="page" @endif
                            @if ($menuItem['new_tab']) target="_blank" rel="noopener" @endif
                        >{{ $menuItem['label'] }}</a>
                    @endforeach
                @else
                    <a class="inline-flex min-h-10 items-center border-b-2 transition hover:border-[var(--storefront-brand)] hover:text-gray-950 dark:hover:text-white {{ $isCatalogCurrent ? 'border-[var(--storefront-brand)] text-gray-950 dark:text-white' : 'border-transparent' }}" href="{{ $productsUrl }}" @if ($isCatalogCurrent) aria-current="page" @endif>Shop all</a>
                @endif

                @if ($navCategories->isNotEmpty())
                    <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
                        <button
                            type="button"
                            @click="open = ! open"
                            :aria-expanded="open.toString()"
                            class="flex min-h-10 items-center gap-1 border-b-2 transition"
                            :class="open ? 'border-[var(--storefront-brand)] text-gray-950 dark:text-white' : 'border-transparent'"
                        >
                            Categories
                            <svg class="h-3.5 w-3.5 transition" :class="open ? 'rotate-180' : ''" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                        </button>
                        <div class="absolute left-0 top-full z-30 w-[min(90vw,640px)] pt-2" x-show="open" x-cloak x-transition>
                            <div class="grid grid-cols-2 gap-1 rounded-xl border border-gray-200 bg-white p-4 shadow-lg sm:grid-cols-3 dark:border-white/10 dark:bg-gray-950">
                                @foreach ($navCategories as $navCategory)
                                    <a class="rounded-lg px-3 py-2 text-sm text-gray-700 transition hover:bg-gray-100 hover:text-gray-950 dark:text-gray-300 dark:hover:bg-white/10 dark:hover:text-white" href="{{ $categoryUrl($navCategory) }}" @if ($isCurrentUrl($categoryUrl($navCategory))) aria-current="page" @endif @click="open = false">
                                        {{ $navCategory->name }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

                @if ($headerMenu->isEmpty())
                    <a class="inline-flex min-h-10 items-center border-b-2 transition hover:border-[var(--storefront-brand)] hover:text-gray-950 dark:hover:text-white {{ $isCurrentUrl($trackUrl) ? 'border-[var(--storefront-brand)] text-gray-950 dark:text-white' : 'border-transparent' }}" href="{{ $trackUrl }}" @if ($isCurrentUrl($trackUrl)) aria-current="page" @endif>Track order</a>
                @endif
            </nav>
        </div>

        <div class="border-t border-gray-100 px-4 py-2 md:hidden dark:border-gray-800">
            <form class="mx-auto w-full max-w-7xl" role="search" method="GET" action="{{ $productsUrl }}">
                <label class="sr-only" for="storefront-search-mobile">Search products</label>
                <div class="relative">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.34-4.34M19 11a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z"/></svg>
                    <input
                        id="storefront-search-mobile"
                        type="search"
                        name="q"
                        value="{{ $search ?? request('q') }}"
                        placeholder="Search products…"
                        autocomplete="off"
                        enterkeyhint="search"
                        class="min-h-11 w-full rounded-full border border-gray-200 bg-gray-50 py-2.5 pl-9 pr-4 text-sm outline-none transition focus:border-[var(--storefront-brand)] focus:bg-white focus:ring-1 focus:ring-[var(--storefront-brand)] dark:border-white/10 dark:bg-white/5 dark:text-white"
                    >
                </div>
            </form>
        </div>

        <div id="storefront-mobile-menu" data-mobile-menu class="hidden border-t border-gray-200 bg-white lg:hidden dark:border-white/10 dark:bg-gray-950" hidden>
            <nav class="mx-auto flex w-full max-w-7xl flex-col gap-1 px-4 py-3" aria-label="Mobile menu">
                @if ($headerMenu->isNotEmpty())
                    @foreach ($headerMenu as $menuItem)
                        <a class="rounded-lg px-3 py-3 text-sm font-medium text-gray-700 transition hover:bg-gray-100 hover:text-gray-950 dark:text-gray-300 dark:hover:bg-white/10 dark:hover:text-white" href="{{ $menuItem['url'] }}" @if ($isCurrentUrl($menuItem['url'])) aria-current="page" @endif @if ($menuItem['new_tab']) target="_blank" rel="noopener" @endif>{{ $menuItem['label'] }}</a>
                    @endforeach
                @else
                    <a class="rounded-lg px-3 py-3 text-sm font-medium text-gray-700 transition hover:bg-gray-100 hover:text-gray-950 dark:text-gray-300 dark:hover:bg-white/10 dark:hover:text-white" href="{{ $productsUrl }}" @if ($isCatalogCurrent) aria-current="page" @endif>Shop all</a>
                    <a class="rounded-lg px-3 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-100 hover:text-gray-950 dark:text-gray-300 dark:hover:bg-white/10 dark:hover:text-white" href="{{ $trackUrl }}" @if ($isCurrentUrl($trackUrl)) aria-current="page" @endif>Track order</a>
                    @if ($accountsEnabled)
                        <a class="rounded-lg px-3 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-100 hover:text-gray-950 dark:text-gray-300 dark:hover:bg-white/10 dark:hover:text-white" href="{{ $mobileAccountUrl }}" @if ($isAccountCurrent) aria-current="page" @endif>
                            {{ $authCustomer ? 'My account' : 'Log in / Register' }}
                        </a>
                    @endif
                @endif
                @if ($navCategories->isNotEmpty())
                    <div class="mt-2 border-t border-gray-100 pt-2 dark:border-white/10">
                        <div class="px-3 pb-1 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Categories</div>
                        <div class="grid grid-cols-2 gap-1">
                            @foreach ($navCategories as $navCategory)
                                <a class="rounded-lg px-3 py-2 text-sm text-gray-600 transition hover:bg-gray-100 hover:text-gray-950 dark:text-gray-400 dark:hover:bg-white/10 dark:hover:text-white" href="{{ $categoryUrl($navCategory) }}" @if ($isCurrentUrl($categoryUrl($navCategory))) aria-current="page" @endif>{{ $navCategory->name }}</a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </nav>
        </div>
    </header>

    <main id="main-content" class="pb-16 sm:pb-0">
        @if (session('storefront_status'))
            <div class="mx-auto mt-3 w-full max-w-7xl px-4 sm:px-5 lg:px-6">
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-medium text-emerald-800 dark:border-emerald-400/20 dark:bg-emerald-400/10 dark:text-emerald-200" role="status" aria-live="polite" aria-atomic="true">
                    {{ session('storefront_status') }}
                </div>
            </div>
        @endif
        @yield('content')
    </main>

    <footer class="storefront-footer mb-16 mt-12 border-t border-gray-200 bg-gray-50 sm:mb-0 dark:border-white/10 dark:bg-white/[0.02]">
        @if ($sisterCompanies->isNotEmpty())
            <div class="border-b border-gray-200 dark:border-white/10">
                <div class="mx-auto w-full max-w-7xl px-4 py-5 sm:px-5 lg:px-6">
                    <div class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Our other brands</div>
                    <div class="mt-3 flex flex-wrap gap-3">
                        @foreach ($sisterCompanies as $sisterCompany)
                            <a class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition hover:border-[var(--storefront-brand)] hover:text-gray-950 dark:border-white/10 dark:text-gray-300 dark:hover:text-white" href="https://{{ $sisterCompany->domain }}" target="_blank" rel="noopener">
                                {{ $sisterCompany->name }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
        <div class="mx-auto grid w-full max-w-7xl gap-7 px-4 py-8 sm:px-5 md:grid-cols-3 lg:px-6">
            <div>
                <div class="text-lg font-semibold tracking-tight">{{ $company->name }}</div>
                <p class="mt-3 max-w-sm text-sm leading-6 text-gray-500 dark:text-gray-400">
                    Browse curated products, place direct orders, and track storefront purchases from {{ $company->name }}.
                </p>
            </div>
            @if ($footerMenu->isNotEmpty())
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Quick links</div>
                    <nav class="mt-3 flex flex-col gap-2" aria-label="Footer menu">
                        @foreach ($footerMenu as $menuItem)
                            <a class="text-sm text-gray-600 transition hover:text-[var(--storefront-brand)] dark:text-gray-400" href="{{ $menuItem['url'] }}" @if ($isCurrentUrl($menuItem['url'])) aria-current="page" @endif @if ($menuItem['new_tab']) target="_blank" rel="noopener" @endif>
                                {{ $menuItem['label'] }}
                            </a>
                        @endforeach
                    </nav>
                </div>
            @elseif ($footerPages->isNotEmpty())
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Pages</div>
                    <nav class="mt-3 flex flex-col gap-2" aria-label="Storefront pages">
                        @foreach ($footerPages as $footerPage)
                            <a class="text-sm text-gray-600 transition hover:text-[var(--storefront-brand)] dark:text-gray-400" href="{{ $pageUrl($footerPage->slug) }}" @if ($isCurrentUrl($pageUrl($footerPage->slug))) aria-current="page" @endif>
                                {{ $footerPage->title }}
                            </a>
                        @endforeach
                    </nav>
                </div>
            @endif
            <div>
                <div class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Contact</div>
                <div class="mt-3 flex flex-col items-start gap-3">
                    <a class="text-sm text-gray-600 transition hover:text-[var(--storefront-brand)] dark:text-gray-400" href="{{ isset($previewSlug) ? route('storefront.preview.contact', $previewSlug) : route('storefront.contact') }}">
                        Contact us
                    </a>
                    <a class="text-sm text-gray-600 transition hover:text-[var(--storefront-brand)] dark:text-gray-400" href="{{ isset($previewSlug) ? route('storefront.preview.complaints.show', $previewSlug) : route('storefront.complaints.show') }}">
                        Product complaint
                    </a>
                    <a class="text-sm text-gray-600 transition hover:text-[var(--storefront-brand)] dark:text-gray-400" href="{{ isset($previewSlug) ? route('storefront.preview.reseller.show', $previewSlug) : route('storefront.reseller.show') }}">
                        Become a reseller
                    </a>
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

    <nav class="storefront-mobile-nav fixed inset-x-0 bottom-0 z-40 grid grid-cols-4 border-t border-gray-200 bg-white/95 backdrop-blur sm:hidden dark:border-white/10 dark:bg-gray-950/95" style="padding-bottom: env(safe-area-inset-bottom);" aria-label="Mobile navigation">
        <a class="flex flex-col items-center gap-1 py-2.5 text-[11px] font-medium {{ $isHomeCurrent ? 'text-[var(--storefront-brand)]' : 'text-gray-600 dark:text-gray-300' }}" href="{{ $homeUrl }}" @if ($isHomeCurrent) aria-current="page" @endif>
            <svg class="h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="m3 12 8.954-8.19a1.5 1.5 0 0 1 2.092 0L23 12M5.25 9.75V19.5a1.5 1.5 0 0 0 1.5 1.5H9.75v-6a1.5 1.5 0 0 1 1.5-1.5h1.5a1.5 1.5 0 0 1 1.5 1.5v6h3a1.5 1.5 0 0 0 1.5-1.5V9.75"/></svg>
            Home
        </a>
        <a class="flex flex-col items-center gap-1 py-2.5 text-[11px] font-medium {{ $isCatalogCurrent ? 'text-[var(--storefront-brand)]' : 'text-gray-600 dark:text-gray-300' }}" href="{{ $productsUrl }}" @if ($isCatalogCurrent) aria-current="page" @endif>
            <svg class="h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5"/></svg>
            Catalog
        </a>
        <a class="relative flex flex-col items-center gap-1 py-2.5 text-[11px] font-medium {{ $isCartCurrent ? 'text-[var(--storefront-brand)]' : 'text-gray-600 dark:text-gray-300' }}" href="{{ $cartUrl }}" @if ($isCartCurrent) aria-current="page" @endif>
            <svg class="h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.836l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 1.874-4.708 2.25-7.183a1.125 1.125 0 0 0-1.11-1.317H5.25M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/></svg>
            @if ($cartCount > 0)
                <span class="absolute right-4 top-1 grid h-4 min-w-4 place-items-center rounded-full bg-[var(--storefront-brand)] px-1 text-[10px] font-semibold text-white">{{ $cartCount }}</span>
            @endif
            Cart
        </a>
        <a class="flex flex-col items-center gap-1 py-2.5 text-[11px] font-medium {{ $isAccountCurrent ? 'text-[var(--storefront-brand)]' : 'text-gray-600 dark:text-gray-300' }}" href="{{ $mobileAccountUrl }}" @if ($isAccountCurrent) aria-current="page" @endif>
            @if ($authCustomer)
                <span class="grid h-5 w-5 place-items-center rounded-full bg-[var(--storefront-brand)] text-[10px] font-semibold text-white" aria-hidden="true">{{ mb_strtoupper(mb_substr($authCustomer->name, 0, 1)) }}</span>
            @elseif (! $accountsEnabled)
                <svg class="h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 4.5A6.75 6.75 0 1 0 18 11.25M15.75 3v4.5h4.5M14.25 14.25l4.5 4.5"/></svg>
            @else
                <svg class="h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
            @endif
            {{ $accountsEnabled ? 'Account' : 'Track' }}
        </a>
    </nav>

    <script>
        (function () {
            var toggle = document.querySelector('[data-mobile-menu-toggle]');
            var menu = document.querySelector('[data-mobile-menu]');
            if (! toggle || ! menu) { return; }

            var isOpen = function () {
                return toggle.getAttribute('aria-expanded') === 'true';
            };
            var setOpen = function (open, restoreFocus) {
                menu.hidden = ! open;
                menu.classList.toggle('hidden', ! open);
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                toggle.setAttribute('aria-label', open ? toggle.dataset.closeLabel : toggle.dataset.openLabel);
                toggle.querySelector('[data-mobile-menu-icon-open]').classList.toggle('hidden', open);
                toggle.querySelector('[data-mobile-menu-icon-close]').classList.toggle('hidden', ! open);

                if (open) {
                    window.requestAnimationFrame(function () {
                        var firstItem = menu.querySelector('a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])');
                        if (firstItem) { firstItem.focus(); }
                    });
                } else if (restoreFocus) {
                    toggle.focus();
                }
            };

            toggle.addEventListener('click', function () {
                setOpen(! isOpen(), false);
            });

            menu.addEventListener('click', function (event) {
                if (event.target.closest('a[href]')) { setOpen(false, false); }
            });

            document.addEventListener('click', function (event) {
                if (isOpen() && ! menu.contains(event.target) && ! toggle.contains(event.target)) {
                    setOpen(false, false);
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && isOpen()) {
                    event.preventDefault();
                    setOpen(false, true);
                }
            });

            var desktopQuery = window.matchMedia('(min-width: 1024px)');
            var closeAtDesktop = function (event) {
                if (event.matches && isOpen()) { setOpen(false, false); }
            };
            if (desktopQuery.addEventListener) {
                desktopQuery.addEventListener('change', closeAtDesktop);
            } else {
                desktopQuery.addListener(closeAtDesktop);
            }
        })();

        (function () {
            var toggle = document.querySelector('[data-theme-toggle]');
            if (! toggle) { return; }

            var syncIcons = function () {
                var dark = document.documentElement.classList.contains('dark');
                document.querySelectorAll('[data-theme-icon-light]').forEach(function (el) { el.classList.toggle('hidden', dark); });
                document.querySelectorAll('[data-theme-icon-dark]').forEach(function (el) { el.classList.toggle('hidden', ! dark); });
                document.querySelectorAll('[data-theme-toggle-label]').forEach(function (el) { el.textContent = dark ? 'Light mode' : 'Dark mode'; });
                toggle.setAttribute('aria-label', dark ? toggle.dataset.lightLabel : toggle.dataset.darkLabel);
                toggle.setAttribute('aria-pressed', dark ? 'true' : 'false');
                toggle.title = dark ? toggle.dataset.lightLabel : toggle.dataset.darkLabel;
            };

            toggle.addEventListener('click', function () {
                var dark = ! document.documentElement.classList.contains('dark');
                document.documentElement.classList.toggle('dark', dark);
                document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
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

        (function () {
            document.addEventListener('submit', function (event) {
                var message = event.target.getAttribute && event.target.getAttribute('data-confirm');
                if (message && ! window.confirm(message)) {
                    event.preventDefault();
                }
            });
        })();
    </script>
</body>
</html>
