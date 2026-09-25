{{-- Marketplace Pro mobile bottom navigation: Home · Categories · Search · Cart · Account. --}}
<nav class="storefront-mobile-nav mp-mobile-nav fixed inset-x-0 bottom-0 z-30 grid grid-cols-5 lg:hidden" style="padding-bottom: env(safe-area-inset-bottom);" aria-label="{{ __('Mobile navigation') }}">
    <a class="mp-mobile-nav-item {{ $isHomeCurrent ? 'is-active' : '' }}" href="{{ $homeUrl }}" @if ($isHomeCurrent) aria-current="page" @endif>
        <svg class="h-5 w-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="m3 12 8.95-8.19a1.5 1.5 0 0 1 2.1 0L23 12M5.25 9.75v9.75a1.5 1.5 0 0 0 1.5 1.5h3v-6a1.5 1.5 0 0 1 1.5-1.5h1.5a1.5 1.5 0 0 1 1.5 1.5v6h3a1.5 1.5 0 0 0 1.5-1.5V9.75"/></svg>
        {{ __('Home') }}
    </a>
    <button type="button" class="mp-mobile-nav-item" x-data @click="$dispatch('mp-open-categories')" aria-controls="mp-category-drawer">
        <svg class="h-5 w-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5h6v6H4zM14 5h6v6h-6zM4 15h6v6H4zM14 15h6v6h-6z"/></svg>
        {{ __('Categories') }}
    </button>
    <button type="button" class="mp-mobile-nav-item {{ $isCatalogCurrent ? 'is-active' : '' }}" x-data @click="window.scrollTo({ top: 0 }); document.getElementById('mp-search-mobile')?.focus()" aria-controls="mp-search-mobile">
        <svg class="h-5 w-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" d="m21 21-4.3-4.3M19 11a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z"/></svg>
        {{ __('Search') }}
    </button>
    <a class="mp-mobile-nav-item relative {{ $isCartCurrent ? 'is-active' : '' }}" href="{{ $cartUrl }}" @if ($isCartCurrent) aria-current="page" @endif>
        <svg class="h-5 w-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.39c.51 0 .95.34 1.09.84l.38 1.43M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.22c1.12-2.3 1.87-4.7 2.25-7.18a1.13 1.13 0 0 0-1.11-1.32H5.25M7.5 14.25 5.1 5.27M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/></svg>
        @if ($cartCount > 0)
            <span class="mp-mobile-nav-badge">{{ $cartCount }}</span>
        @endif
        {{ __('Cart') }}
    </a>
    <a class="mp-mobile-nav-item {{ $isAccountCurrent ? 'is-active' : '' }}" href="{{ $mobileAccountUrl }}" @if ($isAccountCurrent) aria-current="page" @endif>
        <svg class="h-5 w-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M17.98 18.73A7.49 7.49 0 0 0 12 15.75a7.49 7.49 0 0 0-5.98 2.98m11.96 0a9 9 0 1 0-11.96 0m11.96 0A8.97 8.97 0 0 1 12 21a8.97 8.97 0 0 1-5.98-2.27M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
        {{ $accountsEnabled ? __('Account') : __('Track') }}
    </a>
</nav>
