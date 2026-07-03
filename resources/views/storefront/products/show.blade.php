@extends('storefront.layout')

@section('content')
    @php
        $categoryUrl = $product->category
            ? (isset($previewSlug) ? route('storefront.preview.categories.show', [$previewSlug, $product->category->slug]) : route('storefront.categories.show', $product->category->slug))
            : null;
        $productsUrl = isset($previewSlug) ? route('storefront.preview.products.index', $previewSlug) : route('storefront.products.index');
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

    <section class="mx-auto grid w-full max-w-7xl gap-10 px-4 py-8 sm:px-6 lg:grid-cols-[0.95fr_1.05fr] lg:px-8">
        <div class="lg:sticky lg:top-24 lg:self-start">
            @if ($product->image)
                <img class="aspect-square w-full rounded-2xl border border-gray-200 bg-white object-cover dark:border-white/10 dark:bg-white/5" src="{{ asset('storage/'.$product->image) }}" alt="{{ $product->name }}">
            @else
                <div class="grid aspect-square w-full place-items-center rounded-2xl border border-gray-200 bg-gray-100 text-8xl font-semibold text-[var(--storefront-brand)] dark:border-white/10 dark:bg-white/5">
                    {{ mb_substr($product->name, 0, 1) }}
                </div>
            @endif
        </div>

        <div>
            <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">{{ $product->name }}</h1>
            <div class="mt-3 text-2xl font-semibold text-gray-950 dark:text-white">BDT {{ number_format($product->selling_price, 2) }}</div>
            <p class="mt-5 max-w-xl text-base leading-7 text-gray-600 dark:text-gray-300">
                {{ $product->description ?: 'Product details will be updated soon. Contact us for specifications, availability, and delivery details.' }}
            </p>

            <dl class="mt-6 grid grid-cols-3 gap-3 border-y border-gray-200 py-5 text-sm dark:border-white/10">
                <div>
                    <dt class="text-gray-400">Stock</dt>
                    <dd class="mt-1 font-semibold">{{ (int) $product->stock }}</dd>
                </div>
                <div>
                    <dt class="text-gray-400">SKU</dt>
                    <dd class="mt-1 truncate font-semibold">{{ $product->sku }}</dd>
                </div>
                <div>
                    <dt class="text-gray-400">Status</dt>
                    <dd class="mt-1 font-semibold {{ $product->stock > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                        {{ $product->stock > 0 ? 'Available' : 'Out of stock' }}
                    </dd>
                </div>
            </dl>

            <form class="mt-6 flex flex-wrap items-center gap-3" method="POST" action="{{ isset($previewSlug) ? route('storefront.preview.cart.add', [$previewSlug, $product->slug]) : route('storefront.cart.add', $product->slug) }}">
                @csrf
                <div class="flex items-center rounded-lg border border-gray-300 dark:border-white/15" data-qty-stepper>
                    <button type="button" data-qty-decrement class="grid h-12 w-11 place-items-center text-lg text-gray-600 hover:text-gray-950 dark:text-gray-300 dark:hover:text-white" aria-label="Decrease quantity">&minus;</button>
                    <label class="sr-only" for="quantity">Quantity</label>
                    <input id="quantity" data-qty-input class="h-12 w-14 border-x border-gray-300 bg-transparent text-center text-sm font-semibold text-gray-950 outline-none dark:border-white/15 dark:text-white" type="number" name="quantity" value="1" min="1" max="{{ max(1, (int) $product->stock) }}">
                    <button type="button" data-qty-increment class="grid h-12 w-11 place-items-center text-lg text-gray-600 hover:text-gray-950 dark:text-gray-300 dark:hover:text-white" aria-label="Increase quantity">+</button>
                </div>
                <button type="submit" class="inline-flex h-12 items-center rounded-lg bg-gray-950 px-7 text-sm font-medium text-white transition hover:bg-[var(--storefront-brand)] disabled:cursor-not-allowed disabled:opacity-50 dark:bg-white dark:text-gray-950" @disabled($product->stock < 1)>
                    {{ $product->stock > 0 ? 'Add to cart' : 'Out of stock' }}
                </button>
                @if ($setting->whatsapp_number)
                    <a class="inline-flex h-12 items-center rounded-lg border border-gray-300 px-7 text-sm font-medium text-gray-900 transition hover:border-gray-950 dark:border-white/15 dark:text-white dark:hover:border-white" href="https://wa.me/{{ preg_replace('/\D+/', '', $setting->whatsapp_number) }}?text={{ rawurlencode('I am interested in '.$product->name) }}" target="_blank" rel="noopener">
                        Ask on WhatsApp
                    </a>
                @endif
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
@endsection
