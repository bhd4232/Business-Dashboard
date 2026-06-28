@extends('storefront.layout')

@section('content')
    <section class="border-b border-stone-200 bg-white dark:border-white/10 dark:bg-stone-950">
        <div class="mx-auto w-full max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
            <p class="text-sm font-black uppercase tracking-[0.22em] text-[var(--storefront-brand)]">Shopping cart</p>
            <h1 class="mt-3 text-4xl font-black tracking-[-0.05em] sm:text-6xl">Your cart</h1>
            <p class="mt-4 max-w-2xl text-lg leading-8 text-stone-600 dark:text-stone-300">
                Review selected products before checkout. Checkout will create an ERP storefront order in the next Part 4 step.
            </p>
        </div>
    </section>

    <section class="mx-auto grid w-full max-w-7xl gap-8 px-4 py-12 sm:px-6 lg:grid-cols-[1fr_380px] lg:px-8">
        <div class="space-y-4">
            @forelse ($items as $item)
                @php
                    $product = $item['product'];
                    $updateUrl = isset($previewSlug) ? route('storefront.preview.cart.update', [$previewSlug, $product->slug]) : route('storefront.cart.update', $product->slug);
                    $removeUrl = isset($previewSlug) ? route('storefront.preview.cart.remove', [$previewSlug, $product->slug]) : route('storefront.cart.remove', $product->slug);
                    $productUrl = isset($previewSlug) ? route('storefront.preview.products.show', [$previewSlug, $product->slug]) : route('storefront.products.show', $product->slug);
                @endphp
                <article class="grid gap-5 rounded-[2rem] border border-stone-200 bg-white p-4 shadow-sm sm:grid-cols-[120px_1fr] dark:border-white/10 dark:bg-white/5">
                    <a href="{{ $productUrl }}" class="overflow-hidden rounded-3xl bg-stone-100 dark:bg-stone-900">
                        @if ($product->image)
                            <img class="aspect-square h-full w-full object-cover" src="{{ asset('storage/'.$product->image) }}" alt="{{ $product->name }}">
                        @else
                            <div class="grid aspect-square h-full w-full place-items-center text-4xl font-black text-[var(--storefront-brand)]">{{ mb_substr($product->name, 0, 1) }}</div>
                        @endif
                    </a>
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div class="text-xs font-black uppercase tracking-widest text-stone-500">{{ $product->category?->name ?? 'Product' }}</div>
                            <a href="{{ $productUrl }}" class="mt-1 block text-xl font-black tracking-tight">{{ $product->name }}</a>
                            <div class="mt-2 text-sm font-bold text-stone-500 dark:text-stone-400">BDT {{ number_format($item['unit_price'], 2) }} each · {{ (int) $product->stock }} in stock</div>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <form method="POST" action="{{ $updateUrl }}" class="flex items-center gap-2">
                                @csrf
                                @method('PATCH')
                                <label class="sr-only" for="quantity-{{ $product->id }}">Quantity</label>
                                <input id="quantity-{{ $product->id }}" class="h-11 w-20 rounded-full border border-stone-300 bg-white px-3 text-center text-sm font-black dark:border-white/15 dark:bg-white/10" type="number" name="quantity" min="0" max="{{ max(1, (int) $product->stock) }}" value="{{ $item['quantity'] }}">
                                <button class="rounded-full bg-stone-950 px-4 py-2 text-xs font-black text-white dark:bg-white dark:text-stone-950" type="submit">Update</button>
                            </form>
                            <form method="POST" action="{{ $removeUrl }}">
                                @csrf
                                @method('DELETE')
                                <button class="rounded-full border border-stone-300 px-4 py-2 text-xs font-black text-stone-600 transition hover:border-red-500 hover:text-red-600 dark:border-white/15 dark:text-stone-300" type="submit">Remove</button>
                            </form>
                            <div class="w-full text-right text-lg font-black text-[var(--storefront-brand)]">BDT {{ number_format($item['subtotal'], 2) }}</div>
                        </div>
                    </div>
                </article>
            @empty
                <div class="rounded-[2rem] border border-dashed border-stone-300 bg-white p-12 text-center dark:border-white/15 dark:bg-white/5">
                    <h2 class="text-2xl font-black">Your cart is empty</h2>
                    <p class="mt-3 text-stone-500 dark:text-stone-400">Add products from the catalog to start a storefront order.</p>
                    <a class="mt-6 inline-flex rounded-full bg-[var(--storefront-brand)] px-6 py-3 text-sm font-black text-white" href="{{ isset($previewSlug) ? route('storefront.preview.products.index', $previewSlug) : route('storefront.products.index') }}">Browse products</a>
                </div>
            @endforelse
        </div>

        <aside class="h-fit rounded-[2rem] border border-stone-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-white/5">
            <h2 class="text-2xl font-black">Order summary</h2>
            <div class="mt-6 space-y-4 text-sm font-bold text-stone-600 dark:text-stone-300">
                <div class="flex justify-between">
                    <span>Items</span>
                    <span>{{ $items->sum('quantity') }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Subtotal</span>
                    <span>BDT {{ number_format($subtotal, 2) }}</span>
                </div>
                <div class="flex justify-between text-stone-400">
                    <span>Delivery</span>
                    <span>Calculated at checkout</span>
                </div>
            </div>
            <div class="mt-6 border-t border-stone-200 pt-6 dark:border-white/10">
                <div class="flex justify-between text-xl font-black">
                    <span>Total</span>
                    <span>BDT {{ number_format($subtotal, 2) }}</span>
                </div>
                @if ($items->isNotEmpty())
                    <a class="mt-6 flex w-full justify-center rounded-full bg-[var(--storefront-brand)] px-6 py-4 text-sm font-black text-white shadow-xl shadow-stone-900/10 transition hover:-translate-y-0.5" href="{{ isset($previewSlug) ? route('storefront.preview.checkout.show', $previewSlug) : route('storefront.checkout.show') }}">
                        Continue to checkout
                    </a>
                @else
                    <button class="mt-6 w-full cursor-not-allowed rounded-full bg-stone-950 px-6 py-4 text-sm font-black text-white opacity-70 dark:bg-white dark:text-stone-950" type="button" disabled>
                        Add products first
                    </button>
                @endif
            </div>
        </aside>
    </section>
@endsection
