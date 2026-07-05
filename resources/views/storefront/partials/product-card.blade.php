@php
    $productUrl = isset($previewSlug) ? route('storefront.preview.products.show', [$previewSlug, $product->slug]) : route('storefront.products.show', $product->slug);
    $cartAddUrl = isset($previewSlug) ? route('storefront.preview.cart.add', [$previewSlug, $product->slug]) : route('storefront.cart.add', $product->slug);
@endphp

<article class="group overflow-hidden rounded-xl border border-gray-200 bg-white transition hover:border-gray-300 hover:shadow-md dark:border-white/10 dark:bg-white/5">
    <div class="relative overflow-hidden bg-gray-100 dark:bg-white/5">
        <a href="{{ $productUrl }}" class="block">
            @if ($product->image)
                <img class="aspect-square w-full object-cover transition duration-300 group-hover:scale-105" src="{{ asset('storage/'.$product->image) }}" alt="{{ $product->name }}">
            @else
                <div class="grid aspect-square w-full place-items-center text-5xl font-semibold text-[var(--storefront-brand)]">
                    {{ mb_substr($product->name, 0, 1) }}
                </div>
            @endif
        </a>
        @if ($product->stock < 1)
            <div class="absolute inset-x-0 bottom-0 {{ $product->is_preorder ? 'bg-[var(--storefront-brand)]/90' : 'bg-gray-950/80' }} px-3 py-1.5 text-center text-xs font-semibold uppercase tracking-wide text-white">
                {{ $product->is_preorder ? 'Pre-order' : 'Out of stock' }}
            </div>
        @endif

        <form class="absolute bottom-3 right-3 opacity-0 transition group-hover:opacity-100 focus-within:opacity-100" method="POST" action="{{ $cartAddUrl }}">
            @csrf
            <input type="hidden" name="quantity" value="{{ $product->effectiveMoq() }}">
            <button
                class="grid h-10 w-10 place-items-center rounded-full bg-white text-gray-900 shadow-md transition hover:bg-gray-950 hover:text-white disabled:cursor-not-allowed disabled:opacity-50 dark:bg-gray-900 dark:text-white"
                type="submit"
                title="Quick add to cart"
                @disabled($product->stock < 1 && ! $product->is_preorder)
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m-7-7h14"/></svg>
            </button>
        </form>
    </div>
    <div class="p-4">
        <div class="text-xs font-medium text-gray-400">{{ $product->category?->name ?? 'Product' }}</div>
        <a href="{{ $productUrl }}">
            <h3 class="mt-1 line-clamp-2 min-h-10 text-sm font-semibold leading-5 text-gray-900 dark:text-white">{{ $product->name }}</h3>
        </a>
        <div class="mt-2 flex items-center justify-between gap-3">
            <div class="text-base font-semibold text-gray-950 dark:text-white">BDT {{ number_format($product->selling_price, 2) }}</div>
            @if ($product->stock > 0 && $product->stock <= 5)
                <span class="text-xs font-medium text-amber-600 dark:text-amber-400">{{ (int) $product->stock }} left</span>
            @endif
        </div>
        @if ($product->effectiveMoq() > 1)
            <div class="mt-2 inline-flex rounded-full bg-gray-100 px-2.5 py-0.5 text-[11px] font-medium text-gray-600 dark:bg-white/10 dark:text-gray-300">
                MOQ {{ $product->effectiveMoq() }} {{ $product->unit ?: 'pcs' }}
            </div>
        @endif
    </div>
</article>
