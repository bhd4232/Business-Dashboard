@extends('storefront.layout')

@section('content')
    <section class="border-b border-gray-200 dark:border-white/10">
        <div class="mx-auto w-full max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">Your cart</h1>
            <p class="mt-3 max-w-2xl text-base text-gray-600 dark:text-gray-300">
                Review selected products before checkout. Your order will be submitted to {{ $company->name }} for review and confirmation.
            </p>
        </div>
    </section>

    <section class="mx-auto grid w-full max-w-7xl gap-8 px-4 py-10 sm:px-6 lg:grid-cols-[1fr_360px] lg:px-8">
        <div class="space-y-4">
            @forelse ($items as $item)
                @php
                    $product = $item['product'];
                    $updateUrl = isset($previewSlug) ? route('storefront.preview.cart.update', [$previewSlug, $product->slug]) : route('storefront.cart.update', $product->slug);
                    $removeUrl = isset($previewSlug) ? route('storefront.preview.cart.remove', [$previewSlug, $product->slug]) : route('storefront.cart.remove', $product->slug);
                    $productUrl = isset($previewSlug) ? route('storefront.preview.products.show', [$previewSlug, $product->slug]) : route('storefront.products.show', $product->slug);
                @endphp
                <article class="grid gap-4 rounded-xl border border-gray-200 bg-white p-4 sm:grid-cols-[96px_1fr] dark:border-white/10 dark:bg-white/5">
                    <a href="{{ $productUrl }}" class="overflow-hidden rounded-lg bg-gray-100 dark:bg-white/5">
                        @if ($product->image)
                            <img class="aspect-square h-full w-full object-cover" src="{{ asset('storage/'.$product->image) }}" alt="{{ $product->name }}">
                        @else
                            <div class="grid aspect-square h-full w-full place-items-center text-3xl font-semibold text-[var(--storefront-brand)]">{{ mb_substr($product->name, 0, 1) }}</div>
                        @endif
                    </a>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div class="text-xs font-medium text-gray-400">{{ $product->category?->name ?? 'Product' }}</div>
                            <a href="{{ $productUrl }}" class="mt-1 block text-base font-semibold tracking-tight text-gray-950 dark:text-white">{{ $product->name }}</a>
                            <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">BDT {{ number_format($item['unit_price'], 2) }} each &middot; {{ (int) $product->stock }} in stock</div>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <form method="POST" action="{{ $updateUrl }}" class="flex items-center gap-2">
                                @csrf
                                @method('PATCH')
                                <div class="flex items-center rounded-lg border border-gray-300 dark:border-white/15" data-qty-stepper>
                                    <button type="button" data-qty-decrement class="grid h-10 w-9 place-items-center text-gray-600 hover:text-gray-950 dark:text-gray-300 dark:hover:text-white" aria-label="Decrease quantity">&minus;</button>
                                    <label class="sr-only" for="quantity-{{ $product->id }}">Quantity</label>
                                    <input id="quantity-{{ $product->id }}" data-qty-input class="h-10 w-12 border-x border-gray-300 bg-transparent text-center text-sm font-semibold dark:border-white/15" type="number" name="quantity" min="0" max="{{ max(1, (int) $product->stock) }}" value="{{ $item['quantity'] }}">
                                    <button type="button" data-qty-increment class="grid h-10 w-9 place-items-center text-gray-600 hover:text-gray-950 dark:text-gray-300 dark:hover:text-white" aria-label="Increase quantity">+</button>
                                </div>
                                <button class="rounded-lg bg-gray-950 px-3 py-2 text-xs font-medium text-white dark:bg-white dark:text-gray-950" type="submit">Update</button>
                            </form>
                            <form method="POST" action="{{ $removeUrl }}">
                                @csrf
                                @method('DELETE')
                                <button class="rounded-lg border border-gray-300 px-3 py-2 text-xs font-medium text-gray-600 transition hover:border-red-400 hover:text-red-600 dark:border-white/15 dark:text-gray-300" type="submit">Remove</button>
                            </form>
                            <div class="w-full text-right text-base font-semibold text-gray-950 dark:text-white">BDT {{ number_format($item['subtotal'], 2) }}</div>
                        </div>
                    </div>
                </article>
            @empty
                <div class="rounded-xl border border-dashed border-gray-300 bg-white p-12 text-center dark:border-white/15 dark:bg-white/5">
                    <h2 class="text-xl font-semibold">Your cart is empty</h2>
                    <p class="mt-2 text-gray-500 dark:text-gray-400">Add products from the catalog to start a storefront order.</p>
                    <a class="mt-6 inline-flex rounded-lg bg-gray-950 px-6 py-3 text-sm font-medium text-white dark:bg-white dark:text-gray-950" href="{{ isset($previewSlug) ? route('storefront.preview.products.index', $previewSlug) : route('storefront.products.index') }}">Browse products</a>
                </div>
            @endforelse
        </div>

        <aside class="h-fit rounded-xl border border-gray-200 bg-white p-6 lg:sticky lg:top-24 dark:border-white/10 dark:bg-white/5">
            <h2 class="text-lg font-semibold">Order summary</h2>
            <div class="mt-5 space-y-3 text-sm text-gray-600 dark:text-gray-300">
                <div class="flex justify-between">
                    <span>Items</span>
                    <span>{{ $items->sum('quantity') }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Subtotal</span>
                    <span>BDT {{ number_format($subtotal, 2) }}</span>
                </div>
                <div class="flex justify-between text-gray-400">
                    <span>Delivery</span>
                    <span>Calculated at checkout</span>
                </div>
            </div>
            <div class="mt-5 border-t border-gray-200 pt-5 dark:border-white/10">
                <div class="flex justify-between text-lg font-semibold">
                    <span>Total</span>
                    <span>BDT {{ number_format($subtotal, 2) }}</span>
                </div>
                @if ($items->isNotEmpty())
                    <a class="mt-5 flex w-full justify-center rounded-lg bg-gray-950 px-6 py-3 text-sm font-medium text-white transition hover:bg-[var(--storefront-brand)] dark:bg-white dark:text-gray-950" href="{{ isset($previewSlug) ? route('storefront.preview.checkout.show', $previewSlug) : route('storefront.checkout.show') }}">
                        Continue to checkout
                    </a>
                @else
                    <button class="mt-5 w-full cursor-not-allowed rounded-lg bg-gray-950 px-6 py-3 text-sm font-medium text-white opacity-60 dark:bg-white dark:text-gray-950" type="button" disabled>
                        Add products first
                    </button>
                @endif
            </div>
        </aside>
    </section>
@endsection
