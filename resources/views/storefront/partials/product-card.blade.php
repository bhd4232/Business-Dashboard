@php
    $productUrl = isset($previewSlug) ? route('storefront.preview.products.show', [$previewSlug, $product->slug]) : route('storefront.products.show', $product->slug);
    $cartAddUrl = isset($previewSlug) ? route('storefront.preview.cart.add', [$previewSlug, $product->slug]) : route('storefront.cart.add', $product->slug);
@endphp

<article class="group overflow-hidden rounded-[2rem] border border-stone-200 bg-white shadow-sm transition duration-200 hover:-translate-y-1 hover:border-[var(--storefront-brand)] hover:shadow-2xl hover:shadow-stone-900/10 dark:border-white/10 dark:bg-white/5">
    <a href="{{ $productUrl }}" class="block">
        <div class="relative overflow-hidden bg-stone-100 dark:bg-stone-900">
            @if ($product->image)
                <img class="aspect-[4/4.6] w-full object-cover transition duration-500 group-hover:scale-105" src="{{ asset('storage/'.$product->image) }}" alt="{{ $product->name }}">
            @else
                <div class="grid aspect-[4/4.6] w-full place-items-center bg-gradient-to-br from-stone-100 via-white to-amber-100 text-6xl font-black text-[var(--storefront-brand)] transition duration-500 group-hover:scale-105 dark:from-stone-900 dark:via-stone-800 dark:to-stone-900">
                    {{ mb_substr($product->name, 0, 1) }}
                </div>
            @endif
            <div class="absolute left-4 top-4 rounded-full bg-white/90 px-3 py-1 text-xs font-black uppercase tracking-widest text-stone-700 shadow-sm backdrop-blur dark:bg-stone-950/80 dark:text-stone-200">
                {{ $product->category?->name ?? 'Product' }}
            </div>
        </div>
    </a>
    <div class="p-5">
        <a href="{{ $productUrl }}">
            <h3 class="line-clamp-2 min-h-12 text-lg font-black leading-6 tracking-tight">{{ $product->name }}</h3>
        </a>
        <div class="mt-3 flex items-center justify-between gap-3">
            <div class="text-xl font-black text-[var(--storefront-brand)]">BDT {{ number_format($product->selling_price, 2) }}</div>
            <span class="rounded-full bg-stone-100 px-3 py-1 text-xs font-black text-stone-600 dark:bg-white/10 dark:text-stone-300">{{ (int) $product->stock }} left</span>
        </div>
        <form class="mt-4" method="POST" action="{{ $cartAddUrl }}">
            @csrf
            <input type="hidden" name="quantity" value="1">
            <button class="w-full rounded-full bg-stone-950 px-4 py-3 text-sm font-black text-white transition hover:-translate-y-0.5 hover:bg-[var(--storefront-brand)] disabled:cursor-not-allowed disabled:opacity-50 dark:bg-white dark:text-stone-950" type="submit" @disabled($product->stock < 1)>
                Add to cart
            </button>
        </form>
    </div>
</article>
