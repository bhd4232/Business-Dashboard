@extends('storefront.layout')

@section('content')
    @php
        $categoryUrl = $product->category
            ? (isset($previewSlug) ? route('storefront.preview.categories.show', [$previewSlug, $product->category->slug]) : route('storefront.categories.show', $product->category->slug))
            : null;
        $productsUrl = isset($previewSlug) ? route('storefront.preview.products.index', $previewSlug) : route('storefront.products.index');

        $galleryImages = collect([$product->image])
            ->merge($product->gallery_images ?? [])
            ->filter()
            ->values();

        $variants = $product->has_variants
            ? $product->activeVariants()->get()
            : collect();

        $variantData = $variants->map(fn ($variant) => [
            'id' => $variant->getKey(),
            'label' => $variant->label(),
            'price' => $variant->effectiveSalePrice(),
            'stock' => (int) $variant->stock,
            'sku' => $variant->sku ?: $product->sku,
            'images' => collect($variant->images ?? [])->map(fn ($img) => asset('storage/'.$img))->values()->all(),
        ])->values();

        $inStock = $product->stock > 0;
    @endphp

    <nav class="mx-auto w-full max-w-7xl px-4 pt-6 text-sm text-gray-500 sm:px-6 lg:px-8 dark:text-gray-400" aria-label="Breadcrumb">
        <a class="hover:text-gray-900 dark:hover:text-white" href="{{ $productsUrl }}">Shop all</a>
        @if ($categoryUrl)
            <span class="mx-2">/</span>
            <a class="hover:text-gray-900 dark:hover:text-white" href="{{ $categoryUrl }}">{{ $product->category->name }}</a>
        @endif
        <span class="mx-2">/</span>
        <span class="text-gray-900 dark:text-white">{{ $product->name }}</span>
    </nav>

    <section class="mx-auto grid w-full max-w-7xl gap-10 px-4 py-8 sm:px-6 lg:grid-cols-[0.95fr_1.05fr] lg:px-8" data-product-page>
        <div class="lg:sticky lg:top-24 lg:self-start">
            @if ($galleryImages->isNotEmpty())
                <img data-main-image class="aspect-square w-full rounded-2xl border border-gray-200 bg-white object-cover dark:border-white/10 dark:bg-white/5" src="{{ asset('storage/'.$galleryImages->first()) }}" alt="{{ $product->name }}">
                <div data-thumbnails class="mt-3 grid grid-cols-5 gap-2 {{ $galleryImages->count() < 2 && $variantData->pluck('images')->flatten()->isEmpty() ? 'hidden' : '' }}">
                    @foreach ($galleryImages as $galleryImage)
                        <button type="button" data-thumb data-src="{{ asset('storage/'.$galleryImage) }}" class="overflow-hidden rounded-lg border border-gray-200 transition hover:border-[var(--storefront-brand)] dark:border-white/10">
                            <img class="aspect-square w-full object-cover" src="{{ asset('storage/'.$galleryImage) }}" alt="{{ $product->name }} photo {{ $loop->iteration }}">
                        </button>
                    @endforeach
                </div>
            @else
                <div class="grid aspect-square w-full place-items-center rounded-2xl border border-gray-200 bg-gray-100 text-8xl font-semibold text-[var(--storefront-brand)] dark:border-white/10 dark:bg-white/5">
                    {{ mb_substr($product->name, 0, 1) }}
                </div>
            @endif
        </div>

        <div>
            <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">{{ $product->name }}</h1>
            <div data-price class="mt-3 text-2xl font-semibold text-gray-950 dark:text-white">BDT {{ number_format($product->selling_price, 2) }}</div>
            <p class="mt-5 max-w-xl text-base leading-7 text-gray-600 dark:text-gray-300">
                {{ $product->description ?: 'Product details will be updated soon. Contact us for specifications, availability, and delivery details.' }}
            </p>

            <dl class="mt-6 grid grid-cols-3 gap-3 border-y border-gray-200 py-5 text-sm dark:border-white/10">
                <div>
                    <dt class="text-gray-400">Stock</dt>
                    <dd data-stock class="mt-1 font-semibold">{{ (int) $product->stock }}</dd>
                </div>
                <div>
                    <dt class="text-gray-400">SKU</dt>
                    <dd data-sku class="mt-1 truncate font-semibold">{{ $product->sku }}</dd>
                </div>
                <div>
                    <dt class="text-gray-400">Status</dt>
                    <dd data-status class="mt-1 font-semibold {{ $inStock ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                        {{ $inStock ? 'Available' : 'Out of stock' }}
                    </dd>
                </div>
            </dl>

            <form class="mt-6" method="POST" action="{{ isset($previewSlug) ? route('storefront.preview.cart.add', [$previewSlug, $product->slug]) : route('storefront.cart.add', $product->slug) }}">
                @csrf

                @if ($variantData->isNotEmpty())
                    <div class="mb-5">
                        <div class="mb-2 text-sm font-semibold text-gray-900 dark:text-white">Choose options and quantities</div>
                        <div class="divide-y divide-gray-200 overflow-hidden rounded-xl border border-gray-200 dark:divide-white/10 dark:border-white/15" data-variant-rows>
                            @foreach ($variantData as $variant)
                                <div class="flex items-center justify-between gap-3 px-4 py-3 {{ $variant['stock'] < 1 ? 'opacity-50' : '' }}" data-variant-row data-variant='@json($variant)'>
                                    <button type="button" data-variant-preview class="min-w-0 flex-1 text-left">
                                        <div class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $variant['label'] }}</div>
                                        <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                            BDT {{ number_format($variant['price'], 2) }}
                                            &middot;
                                            @if ($variant['stock'] > 0)
                                                {{ $variant['stock'] }} in stock
                                            @else
                                                <span class="font-medium text-red-600 dark:text-red-400">Out of stock</span>
                                            @endif
                                        </div>
                                    </button>
                                    @if ($variant['stock'] > 0)
                                        <div class="flex shrink-0 items-center rounded-lg border border-gray-300 dark:border-white/15" data-qty-stepper>
                                            <button type="button" data-qty-decrement class="grid h-10 w-9 place-items-center text-gray-600 hover:text-gray-950 dark:text-gray-300 dark:hover:text-white" aria-label="Decrease quantity">&minus;</button>
                                            <label class="sr-only" for="variant-qty-{{ $variant['id'] }}">Quantity for {{ $variant['label'] }}</label>
                                            <input id="variant-qty-{{ $variant['id'] }}" data-qty-input data-variant-qty class="h-10 w-12 border-x border-gray-300 bg-transparent text-center text-sm font-semibold text-gray-950 outline-none dark:border-white/15 dark:text-white" type="number" name="quantities[{{ $variant['id'] }}]" value="0" min="0" max="{{ $variant['stock'] }}">
                                            <button type="button" data-qty-increment class="grid h-10 w-9 place-items-center text-gray-600 hover:text-gray-950 dark:text-gray-300 dark:hover:text-white" aria-label="Increase quantity">+</button>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        <p data-variant-hint class="mt-2 text-xs text-gray-500 dark:text-gray-400">Set the quantity for each option you want, then add everything to your cart in one click.</p>
                    </div>
                @endif

                <div class="flex flex-wrap items-center gap-3">
                    @if ($variantData->isEmpty())
                        <div class="flex items-center rounded-lg border border-gray-300 dark:border-white/15" data-qty-stepper>
                            <button type="button" data-qty-decrement class="grid h-12 w-11 place-items-center text-lg text-gray-600 hover:text-gray-950 dark:text-gray-300 dark:hover:text-white" aria-label="Decrease quantity">&minus;</button>
                            <label class="sr-only" for="quantity">Quantity</label>
                            <input id="quantity" data-qty-input class="h-12 w-14 border-x border-gray-300 bg-transparent text-center text-sm font-semibold text-gray-950 outline-none dark:border-white/15 dark:text-white" type="number" name="quantity" value="1" min="1" max="{{ max(1, (int) $product->stock) }}">
                            <button type="button" data-qty-increment class="grid h-12 w-11 place-items-center text-lg text-gray-600 hover:text-gray-950 dark:text-gray-300 dark:hover:text-white" aria-label="Increase quantity">+</button>
                        </div>
                    @endif
                    <button type="submit" data-add-to-cart class="inline-flex h-12 items-center rounded-lg bg-gray-950 px-7 text-sm font-medium text-white transition hover:bg-[var(--storefront-brand)] disabled:cursor-not-allowed disabled:opacity-50 dark:bg-white dark:text-gray-950" @disabled(! $inStock || $variantData->isNotEmpty())>
                        {{ $inStock ? 'Add to cart' : 'Out of stock' }}
                    </button>
                    @if ($setting->whatsapp_number)
                        <a class="inline-flex h-12 items-center rounded-lg border border-gray-300 px-7 text-sm font-medium text-gray-900 transition hover:border-gray-950 dark:border-white/15 dark:text-white dark:hover:border-white" href="https://wa.me/{{ preg_replace('/\D+/', '', $setting->whatsapp_number) }}?text={{ rawurlencode('I am interested in '.$product->name) }}" target="_blank" rel="noopener">
                            Ask on WhatsApp
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </section>

    @if ($related->isNotEmpty())
        <section class="mx-auto w-full max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
            <h2 class="mb-6 text-2xl font-semibold tracking-tight">You may also like</h2>
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($related as $relatedProduct)
                    @include('storefront.partials.product-card', ['product' => $relatedProduct])
                @endforeach
            </div>
        </section>
    @endif

    <script>
        (function () {
            var page = document.querySelector('[data-product-page]');
            if (!page) return;

            var mainImage = page.querySelector('[data-main-image]');
            var thumbsWrap = page.querySelector('[data-thumbnails]');
            var baseThumbsHtml = thumbsWrap ? thumbsWrap.innerHTML : '';

            function bindThumbs() {
                if (!thumbsWrap || !mainImage) return;
                thumbsWrap.querySelectorAll('[data-thumb]').forEach(function (thumb) {
                    thumb.addEventListener('click', function () {
                        mainImage.src = thumb.getAttribute('data-src');
                    });
                });
            }

            bindThumbs();

            var rows = page.querySelectorAll('[data-variant-row]');
            if (!rows.length) return;

            var addBtn = page.querySelector('[data-add-to-cart]');
            var qtyInputs = page.querySelectorAll('[data-variant-qty]');

            function refreshAddButton() {
                if (!addBtn) return;
                var total = 0;
                qtyInputs.forEach(function (input) {
                    total += parseInt(input.value, 10) || 0;
                });
                addBtn.disabled = total < 1;
                addBtn.textContent = total > 0
                    ? 'Add to cart (' + total + ')'
                    : 'Add to cart';
            }

            refreshAddButton();

            qtyInputs.forEach(function (input) {
                input.addEventListener('input', refreshAddButton);
                input.addEventListener('change', refreshAddButton);
            });

            // Steppers change values programmatically — watch clicks too.
            page.querySelectorAll('[data-variant-rows] [data-qty-decrement], [data-variant-rows] [data-qty-increment]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    setTimeout(refreshAddButton, 0);
                });
            });

            // Clicking a variant's name previews its images.
            rows.forEach(function (row) {
                var preview = row.querySelector('[data-variant-preview]');
                if (!preview) return;

                preview.addEventListener('click', function () {
                    var variant = JSON.parse(row.getAttribute('data-variant'));

                    if (thumbsWrap && mainImage) {
                        if (variant.images && variant.images.length) {
                            thumbsWrap.classList.remove('hidden');
                            thumbsWrap.innerHTML = variant.images.map(function (src) {
                                return '<button type="button" data-thumb data-src="' + src + '" class="overflow-hidden rounded-lg border border-gray-200 transition hover:border-[var(--storefront-brand)] dark:border-white/10"><img class="aspect-square w-full object-cover" src="' + src + '" alt=""></button>';
                            }).join('');
                            mainImage.src = variant.images[0];
                        } else {
                            thumbsWrap.innerHTML = baseThumbsHtml;
                            var firstThumb = thumbsWrap.querySelector('[data-thumb]');
                            if (firstThumb) mainImage.src = firstThumb.getAttribute('data-src');
                        }
                        bindThumbs();
                    }
                });
            });
        })();
    </script>
@endsection
