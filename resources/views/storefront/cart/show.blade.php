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
                    $variant = $item['variant'] ?? null;
                    $lineStock = $variant ? (int) $variant->stock : (int) $product->stock;
                    $isPreorderLine = ! $variant && (bool) $product->is_preorder;
                    $lineMax = $isPreorderLine
                        ? max($lineStock, \App\Services\StorefrontCart::PREORDER_STOCK_CEILING)
                        : max(1, $lineStock);
                    $lineMin = min($product->effectiveMoq(), $lineMax);
                    $lineImage = $variant && filled($variant->images) ? collect($variant->images)->first() : $product->image;
                    $updateUrl = isset($previewSlug) ? route('storefront.preview.cart.update', [$previewSlug, $product->slug]) : route('storefront.cart.update', $product->slug);
                    $removeUrl = isset($previewSlug) ? route('storefront.preview.cart.remove', [$previewSlug, $product->slug]) : route('storefront.cart.remove', $product->slug);
                    $productUrl = isset($previewSlug) ? route('storefront.preview.products.show', [$previewSlug, $product->slug]) : route('storefront.products.show', $product->slug);
                @endphp
                <article class="grid gap-4 rounded-xl border border-gray-200 bg-white p-4 sm:grid-cols-[96px_1fr] dark:border-white/10 dark:bg-white/5">
                    <a href="{{ $productUrl }}" class="overflow-hidden rounded-lg bg-gray-100 dark:bg-white/5">
                        @if ($lineImage)
                            <img class="aspect-square h-full w-full object-cover" src="{{ \App\Support\StorageUrl::for($lineImage) }}" alt="{{ $product->name }}" width="192" height="192" loading="lazy" decoding="async">
                        @else
                            <div class="grid aspect-square h-full w-full place-items-center text-3xl font-semibold text-[var(--storefront-brand)]">{{ mb_substr($product->name, 0, 1) }}</div>
                        @endif
                    </a>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div class="text-xs font-medium text-gray-400">{{ $product->category?->name ?? 'Product' }}</div>
                            <a href="{{ $productUrl }}" class="mt-1 block text-base font-semibold tracking-tight text-gray-950 dark:text-white">{{ $product->name }}</a>
                            @if ($variant)
                                <div class="mt-0.5 text-xs font-medium text-gray-500 dark:text-gray-400">{{ $variant->label() }}</div>
                            @endif
                            <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                BDT {{ number_format($item['unit_price'], 2) }} each
                                &middot;
                                @if ($isPreorderLine && $lineStock < 1)
                                    Available for pre-order
                                @elseif ($isPreorderLine)
                                    {{ $lineStock }} in stock; additional quantity available for pre-order
                                @else
                                    {{ $lineStock }} in stock
                                @endif
                            </div>
                            @if ($product->effectiveMoq() > 1)
                                <div class="mt-1 text-xs font-medium text-gray-400 dark:text-gray-500">Minimum order: {{ $product->effectiveMoq() }} {{ $product->unit ?: 'pcs' }}</div>
                            @endif
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <form method="POST" action="{{ $updateUrl }}" class="flex items-center gap-2" data-cart-line-form>
                                @csrf
                                @method('PATCH')
                                @if ($variant)
                                    <input type="hidden" name="variant" value="{{ $variant->getKey() }}">
                                @endif
                                <div class="flex items-center rounded-lg border border-gray-300 dark:border-white/15" data-qty-stepper>
                                    <button type="button" data-qty-decrement class="grid h-10 w-9 place-items-center text-gray-600 hover:text-gray-950 dark:text-gray-300 dark:hover:text-white [touch-action:manipulation]" aria-label="Decrease quantity">&minus;</button>
                                    <label class="sr-only" for="quantity-{{ $product->id }}-{{ $variant?->getKey() ?? 0 }}">Quantity</label>
                                    <input id="quantity-{{ $product->id }}-{{ $variant?->getKey() ?? 0 }}" data-qty-input class="h-10 w-12 border-x border-gray-300 bg-transparent text-center text-sm font-semibold outline-none focus:ring-1 focus:ring-[var(--storefront-brand)] dark:border-white/15" type="number" name="quantity" min="{{ $lineMin }}" max="{{ $lineMax }}" value="{{ $item['quantity'] }}" autocomplete="off">
                                    <button type="button" data-qty-increment class="grid h-10 w-9 place-items-center text-gray-600 hover:text-gray-950 dark:text-gray-300 dark:hover:text-white [touch-action:manipulation]" aria-label="Increase quantity">+</button>
                                </div>
                                <button class="rounded-lg bg-[var(--storefront-brand)] px-3 py-2 text-xs font-medium text-white transition hover:opacity-90 disabled:cursor-wait disabled:opacity-60" type="submit" data-pending-label="Updating&hellip;">Update</button>
                            </form>
                            <form method="POST" action="{{ $removeUrl }}" data-confirm="Remove {{ $product->name }} from your cart?" data-cart-line-form>
                                @csrf
                                @method('DELETE')
                                @if ($variant)
                                    <input type="hidden" name="variant" value="{{ $variant->getKey() }}">
                                @endif
                                <button class="rounded-lg border border-gray-300 px-3 py-2 text-xs font-medium text-gray-600 transition hover:border-red-400 hover:text-red-600 disabled:cursor-wait disabled:opacity-60 dark:border-white/15 dark:text-gray-300" type="submit" data-pending-label="Removing&hellip;">Remove</button>
                            </form>
                            <div class="w-full text-right text-base font-semibold text-gray-950 dark:text-white">BDT {{ number_format($item['subtotal'], 2) }}</div>
                        </div>
                    </div>
                </article>
            @empty
                <div class="rounded-xl border border-dashed border-gray-300 bg-white p-12 text-center dark:border-white/15 dark:bg-white/5">
                    <h2 class="text-xl font-semibold">Your cart is empty</h2>
                    <p class="mt-2 text-gray-500 dark:text-gray-400">Add products from the catalog to start a storefront order.</p>
                    <a class="mt-6 inline-flex rounded-lg bg-[var(--storefront-brand)] px-6 py-3 text-sm font-medium text-white transition hover:opacity-90" href="{{ isset($previewSlug) ? route('storefront.preview.products.index', $previewSlug) : route('storefront.products.index') }}">Browse products</a>
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
                    <a class="mt-5 flex w-full justify-center rounded-lg bg-[var(--storefront-brand)] px-6 py-3 text-sm font-medium text-white transition hover:opacity-90" href="{{ isset($previewSlug) ? route('storefront.preview.checkout.show', $previewSlug) : route('storefront.checkout.show') }}">
                        Continue to checkout
                    </a>
                @else
                    <button class="mt-5 w-full cursor-not-allowed rounded-lg bg-[var(--storefront-brand)] px-6 py-3 text-sm font-medium text-white opacity-60" type="button" disabled>
                        Add products first
                    </button>
                @endif
            </div>
        </aside>
    </section>

    <script>
        document.addEventListener('submit', function (event) {
            var form = event.target.closest && event.target.closest('[data-cart-line-form]');
            if (!form) return;

            if (form.dataset.submitting === 'true') {
                event.preventDefault();
                return;
            }

            form.dataset.submitting = 'true';

            window.setTimeout(function () {
                if (event.defaultPrevented) {
                    delete form.dataset.submitting;
                    return;
                }

                form.setAttribute('aria-busy', 'true');
                form.querySelectorAll('button[type="submit"]').forEach(function (button) {
                    button.disabled = true;
                    button.setAttribute('aria-busy', 'true');
                    button.textContent = button.getAttribute('data-pending-label') || 'Working…';
                });
            }, 0);
        });
    </script>
@endsection
