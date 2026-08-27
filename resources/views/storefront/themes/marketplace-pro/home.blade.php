@extends(\App\Support\StorefrontThemeRegistry::layoutView($setting->storefrontTheme()))

@section('content')
    @php
        $template = $setting->homepageTemplate();
        $productLimit = $setting->marketplaceProductLimit();
        $visibleProducts = $products->take($productLimit);
        $primaryProduct = $visibleProducts->first();
        $slides = $slides ?? collect();
        $heroImage = $primaryProduct?->image ? \App\Support\CompanyMedia::publicUrl($primaryProduct->image, $company) : null;
        $productsUrl = isset($previewSlug) ? route('storefront.preview.products.index', $previewSlug) : route('storefront.products.index');
        $categoryUrl = fn ($category) => isset($previewSlug)
            ? route('storefront.preview.categories.show', [$previewSlug, $category->slug])
            : route('storefront.categories.show', $category->slug);
        $resellerUrl = isset($previewSlug) ? route('storefront.preview.reseller.show', $previewSlug) : route('storefront.reseller.show');
        $whatsappDigits = preg_replace('/\D+/', '', (string) $setting->whatsapp_number);
        $quoteUrl = $whatsappDigits !== '' ? 'https://wa.me/'.$whatsappDigits : $productsUrl;
        $campaignBadge = $setting->marketplace_campaign_badge ?: ($template === \App\Support\StorefrontThemeRegistry::MARKETPLACE_CAMPAIGN ? 'SEASON-END CLEARANCE' : 'WHOLESALE OFFERS');
        $campaignHeading = $setting->marketplace_campaign_heading ?: match ($template) {
            \App\Support\StorefrontThemeRegistry::MARKETPLACE_CAMPAIGN => 'Wholesale savings across essential business supplies',
            \App\Support\StorefrontThemeRegistry::MARKETPLACE_COMPACT => 'Bulk order season â€” better pricing for growing businesses',
            default => 'Better value on dependable products for your business',
        };
        $campaignSubheading = $setting->marketplace_campaign_subheading ?: 'Order genuine products with transparent pricing, dependable dispatch, and support from '.$company->name.'.';
        $campaignCta = $setting->marketplace_campaign_cta_label ?: ($setting->marketplace_quote_enabled ? 'Request bulk quote' : 'Shop products');
        $trustItems = collect([
            ['title' => $setting->trust_strip_delivery ?: 'Nationwide dispatch', 'subtitle' => 'Fast, trackable delivery', 'icon' => 'truck'],
            ['title' => $setting->trust_strip_return ?: 'Easy replacement', 'subtitle' => 'Clear support process', 'icon' => 'refresh'],
            ['title' => 'Business pricing', 'subtitle' => 'Volume-friendly offers', 'icon' => 'briefcase'],
            ['title' => $setting->trust_strip_payment ?: 'Secure payment', 'subtitle' => 'Flexible payment methods', 'icon' => 'lock'],
        ]);
    @endphp

    <div class="marketplace-pro-home">
        @if ($slides->isNotEmpty())
            @include('storefront.partials.image-banner', ['slides' => $slides])
        @endif

        @if ($slides->isEmpty() && $template === \App\Support\StorefrontThemeRegistry::MARKETPLACE_HERO)
            <section class="marketplace-section marketplace-hero-grid" aria-labelledby="marketplace-hero-title">
                <div class="marketplace-hero-card">
                    @if ($heroImage)
                        <img class="absolute inset-0 h-full w-full object-cover" src="{{ $heroImage }}" alt="" width="1600" height="900" fetchpriority="high">
                    @endif
                    <div class="absolute inset-0 bg-gradient-to-r from-black/90 via-black/65 to-black/10"></div>
                    <div class="relative z-10 flex min-h-[300px] max-w-2xl flex-col justify-center p-6 sm:min-h-[360px] sm:p-10 lg:p-12">
                        <span class="marketplace-badge">{{ $campaignBadge }}</span>
                        <h1 id="marketplace-hero-title" class="mt-4 text-balance text-3xl font-extrabold leading-tight text-white sm:text-4xl lg:text-5xl">{{ $campaignHeading }}</h1>
                        <p class="mt-3 max-w-xl text-sm leading-6 text-white/80 sm:text-base">{{ $campaignSubheading }}</p>
                        <div class="mt-6 flex flex-wrap gap-3">
                            <a class="marketplace-button marketplace-button-accent" href="{{ $setting->marketplace_quote_enabled ? $quoteUrl : $productsUrl }}" @if ($setting->marketplace_quote_enabled && $whatsappDigits !== '') target="_blank" rel="noopener noreferrer" @endif>{{ $campaignCta }}</a>
                            <a class="marketplace-button marketplace-button-ghost" href="{{ $productsUrl }}">Browse catalog</a>
                        </div>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
                    @foreach ($categories->take(2) as $category)
                        <a class="marketplace-promo-card group" href="{{ $categoryUrl($category) }}">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-[var(--storefront-brand)]">Ready to order</p>
                                <h2 class="mt-1 text-lg font-bold">{{ $category->name }}</h2>
                                <span class="mt-4 inline-flex text-sm font-semibold text-[var(--storefront-brand)]">Browse category â†’</span>
                            </div>
                            <div class="h-24 w-24 shrink-0 overflow-hidden rounded-xl bg-gray-100 dark:bg-white/10">
                                @if ($category->image)
                                    <img class="h-full w-full object-cover transition duration-300 group-hover:scale-105" src="{{ \App\Support\CompanyMedia::publicUrl($category->image, $company) }}" alt="" width="192" height="192" loading="lazy">
                                @elseif ($category->icon)
                                    <div class="grid h-full w-full place-items-center text-[var(--storefront-brand)] transition duration-300 group-hover:scale-105">
                                        @include('storefront.partials.category-icon', ['icon' => $category->icon, 'iconClass' => 'h-9 w-9'])
                                    </div>
                                @else
                                    <div class="grid h-full w-full place-items-center text-3xl font-bold text-[var(--storefront-brand)]" data-category-initial="{{ mb_substr($category->name, 0, 1) }}">{{ mb_substr($category->name, 0, 1) }}</div>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @elseif ($slides->isEmpty() && $template === \App\Support\StorefrontThemeRegistry::MARKETPLACE_CAMPAIGN)
            <section class="marketplace-campaign" aria-labelledby="marketplace-campaign-title">
                <div class="marketplace-container py-10 text-center sm:py-14">
                    <span class="marketplace-badge marketplace-badge-light">{{ $campaignBadge }}</span>
                    <h1 id="marketplace-campaign-title" class="mx-auto mt-4 max-w-4xl text-balance text-3xl font-extrabold leading-tight text-white sm:text-4xl lg:text-5xl">{{ $campaignHeading }}</h1>
                    <p class="mx-auto mt-3 max-w-2xl text-sm leading-6 text-white/85 sm:text-base">{{ $campaignSubheading }}</p>
                    <a class="marketplace-button mt-6 bg-white text-gray-950 hover:bg-white/90" href="{{ $setting->marketplace_quote_enabled ? $quoteUrl : $productsUrl }}" @if ($setting->marketplace_quote_enabled && $whatsappDigits !== '') target="_blank" rel="noopener noreferrer" @endif>{{ $campaignCta }}</a>
                    <div class="mx-auto mt-7 grid max-w-2xl grid-cols-3 gap-2 sm:gap-3">
                        <div class="marketplace-stat"><strong>Up to 30%</strong><span>selected offers</span></div>
                        <div class="marketplace-stat"><strong>{{ $products->count() }}+</strong><span>products ready</span></div>
                        <div class="marketplace-stat"><strong>24â€“48h</strong><span>dispatch support</span></div>
                    </div>
                </div>
            </section>
        @endif

        @if ($setting->marketplace_trust_strip_enabled && $template !== \App\Support\StorefrontThemeRegistry::MARKETPLACE_COMPACT)
            <section class="marketplace-section !mt-5" aria-label="Store benefits">
                <div class="marketplace-trust-grid">
                    @foreach ($trustItems as $item)
                        <div class="flex items-center gap-3">
                            <span class="marketplace-trust-icon" aria-hidden="true">
                                @if ($item['icon'] === 'truck')
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M3 6h11v11H3zM14 10h4l3 3v4h-7zM7 20a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm11 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/></svg>
                                @elseif ($item['icon'] === 'refresh')
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 7v5h-5M4 17v-5h5M6.1 9A7 7 0 0 1 18 6l2 2M17.9 15A7 7 0 0 1 6 18l-2-2"/></svg>
                                @elseif ($item['icon'] === 'briefcase')
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 7V5h6v2M4 7h16v12H4zM4 12h16M10 12v2h4v-2"/></svg>
                                @else
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M6 10h12v10H6zM8 10V7a4 4 0 0 1 8 0v3"/></svg>
                                @endif
                            </span>
                            <span><strong class="block text-sm">{{ $item['title'] }}</strong><span class="text-xs text-[var(--storefront-muted-text)] dark:text-[var(--storefront-dark-muted-text)]">{{ $item['subtitle'] }}</span></span>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($template === \App\Support\StorefrontThemeRegistry::MARKETPLACE_COMPACT)
            <div class="marketplace-container marketplace-dense-shell {{ $setting->marketplace_sidebar_enabled ? 'marketplace-dense-shell-with-sidebar' : '' }}">
                @if ($setting->marketplace_sidebar_enabled)
                    <aside class="marketplace-sidebar" aria-label="Product categories">
                        <p class="text-xs font-bold uppercase tracking-wider text-[var(--storefront-muted-text)]">Categories</p>
                        <nav class="mt-3 flex gap-2 overflow-x-auto pb-1 lg:flex-col lg:overflow-visible">
                            @foreach ($categories as $category)
                                <a class="marketplace-sidebar-link" href="{{ $categoryUrl($category) }}">{{ $category->name }}</a>
                            @endforeach
                        </nav>
                        @if ($setting->marketplace_quote_enabled)
                            <div class="mt-5 hidden rounded-xl bg-gray-100 p-4 lg:block dark:bg-white/10">
                                <p class="text-sm font-bold">Need a custom quote?</p>
                                <a class="marketplace-button marketplace-button-dark mt-3 w-full" href="{{ $quoteUrl }}" @if ($whatsappDigits !== '') target="_blank" rel="noopener noreferrer" @endif>Request quote</a>
                            </div>
                        @endif
                    </aside>
                @endif

                <section class="min-w-0 py-5 lg:px-6" aria-labelledby="dense-products-title">
                    @if ($slides->isEmpty())
                        <div class="marketplace-dense-banner">
                            <div><p class="font-bold">{{ $campaignHeading }}</p><p class="mt-1 text-xs text-white/75">{{ $campaignSubheading }}</p></div>
                            <a class="marketplace-button marketplace-button-accent" href="{{ $productsUrl }}">Shop now</a>
                        </div>
                    @endif
                    @if ($setting->marketplace_trust_strip_enabled)
                        <div class="mt-4 flex flex-wrap gap-x-5 gap-y-2 border-b border-[var(--storefront-border)] pb-4 text-xs font-semibold dark:border-[var(--storefront-dark-border)]">
                            @foreach ($trustItems as $item)<span>âœ“ {{ $item['title'] }}</span>@endforeach
                        </div>
                    @endif
                    <div class="mt-5 flex items-end justify-between gap-4">
                        <div><p class="text-xs font-semibold uppercase tracking-wider text-[var(--storefront-brand)]">Wholesale catalog</p><h1 id="dense-products-title" class="mt-1 text-xl font-extrabold sm:text-2xl">All available products</h1></div>
                        <a class="text-sm font-semibold text-[var(--storefront-brand)]" href="{{ $productsUrl }}">View all â†’</a>
                    </div>
                    <div class="marketplace-product-grid marketplace-product-grid-dense mt-4">
                        @forelse ($visibleProducts as $product)
                            @include('storefront.partials.product-card', ['product' => $product])
                        @empty
                            <p class="col-span-full rounded-xl bg-[var(--storefront-surface)] p-8 text-center dark:bg-[var(--storefront-dark-surface)]">No products are available yet.</p>
                        @endforelse
                    </div>
                </section>
            </div>
        @else
            @if ($setting->marketplace_categories_enabled)
                <section class="marketplace-section" aria-labelledby="marketplace-categories-title">
                    <div class="marketplace-section-heading"><h2 id="marketplace-categories-title">Shop by category</h2><a href="{{ $productsUrl }}">See all â†’</a></div>
                    <div class="marketplace-category-grid">
                        @foreach ($categories->take(6) as $category)
                            <a class="marketplace-category-card" href="{{ $categoryUrl($category) }}">
                                <span class="marketplace-category-media">
                                    @if ($category->image)
                                        <img src="{{ \App\Support\CompanyMedia::publicUrl($category->image, $company) }}" alt="" width="144" height="144" loading="lazy">
                                    @elseif ($category->icon)
                                        @include('storefront.partials.category-icon', ['icon' => $category->icon, 'iconClass' => 'h-8 w-8'])
                                    @else
                                        <span data-category-initial="{{ mb_substr($category->name, 0, 1) }}">{{ mb_substr($category->name, 0, 1) }}</span>
                                    @endif
                                </span>
                                <strong>{{ $category->name }}</strong>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            @if ($setting->marketplace_deals_enabled)
                <section class="marketplace-section {{ $template === \App\Support\StorefrontThemeRegistry::MARKETPLACE_CAMPAIGN ? 'marketplace-deals-surface' : '' }}" aria-labelledby="marketplace-deals-title">
                    <div class="marketplace-section-heading"><div class="flex items-center gap-3"><h2 id="marketplace-deals-title">{{ $template === \App\Support\StorefrontThemeRegistry::MARKETPLACE_CAMPAIGN ? 'Flash deals' : 'Featured offers' }}</h2><span class="rounded-md bg-[var(--storefront-secondary)] px-2 py-1 text-[11px] font-bold text-[var(--storefront-secondary-contrast)]">Limited time</span></div><a href="{{ $productsUrl }}">See all â†’</a></div>
                    <div class="marketplace-product-grid">
                        @forelse ($visibleProducts->take(5) as $product)
                            @include('storefront.partials.product-card', ['product' => $product])
                        @empty
                            <p class="col-span-full rounded-xl bg-[var(--storefront-surface)] p-8 text-center dark:bg-[var(--storefront-dark-surface)]">No products are available yet.</p>
                        @endforelse
                    </div>
                </section>
            @endif

            @if ($template === \App\Support\StorefrontThemeRegistry::MARKETPLACE_CAMPAIGN && ($setting->marketplace_bulk_pricing_enabled || $setting->marketplace_business_accounts_enabled))
                <section class="marketplace-section grid gap-4 lg:grid-cols-2">
                    @if ($setting->marketplace_bulk_pricing_enabled)
                        <div class="marketplace-info-card">
                            <h2 class="text-lg font-extrabold">Bulk pricing benefits</h2>
                            <dl class="mt-4 divide-y divide-[var(--storefront-border)] text-sm dark:divide-[var(--storefront-dark-border)]">
                                <div class="flex justify-between gap-4 py-3"><dt>Standard orders</dt><dd class="font-bold">Published price</dd></div>
                                <div class="flex justify-between gap-4 py-3"><dt>Volume orders</dt><dd class="font-bold">Custom quote</dd></div>
                                <div class="flex justify-between gap-4 py-3"><dt>Business accounts</dt><dd class="font-bold">Priority support</dd></div>
                            </dl>
                        </div>
                    @endif
                    @if ($setting->marketplace_business_accounts_enabled)
                        <div class="marketplace-business-card">
                            <div><h2 class="text-lg font-extrabold">Open a business account</h2><p class="mt-2 text-sm leading-6 text-white/75">Get reseller support, volume pricing assistance, and a smoother repeat-order workflow.</p></div>
                            <a class="marketplace-button marketplace-button-accent mt-5 self-start" href="{{ $resellerUrl }}">Get started</a>
                        </div>
                    @endif
                </section>
            @endif

            <section class="marketplace-section" aria-labelledby="marketplace-products-title">
                <div class="marketplace-section-heading"><h2 id="marketplace-products-title">Recommended for your business</h2><a href="{{ $productsUrl }}">View all products â†’</a></div>
                <div class="marketplace-product-grid">
                    @forelse ($visibleProducts->skip(5)->take(5) as $product)
                        @include('storefront.partials.product-card', ['product' => $product])
                    @empty
                        @foreach ($visibleProducts->take(5) as $product)
                            @include('storefront.partials.product-card', ['product' => $product])
                        @endforeach
                    @endforelse
                </div>
            </section>

            @if ($setting->marketplace_business_accounts_enabled && $template === \App\Support\StorefrontThemeRegistry::MARKETPLACE_HERO)
                <section class="marketplace-section">
                    <div class="marketplace-business-strip">
                        <div><h2 class="text-lg font-extrabold sm:text-xl">Built for repeat and wholesale buyers</h2><p class="mt-1 text-sm text-white/70">Apply for a business account and get purchasing support from {{ $company->name }}.</p></div>
                        <a class="marketplace-button marketplace-button-accent" href="{{ $resellerUrl }}">Open business account</a>
                    </div>
                </section>
            @endif
        @endif
    </div>
@endsection
