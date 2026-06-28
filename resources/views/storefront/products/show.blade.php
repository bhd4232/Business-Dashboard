@extends('storefront.layout')

@section('content')
    <section class="mx-auto grid w-full max-w-7xl gap-10 px-4 py-12 sm:px-6 lg:grid-cols-[0.95fr_1.05fr] lg:px-8 lg:py-16">
        <div class="lg:sticky lg:top-28 lg:self-start">
            @if ($product->image)
                <img class="aspect-square w-full rounded-[2.5rem] border border-stone-200 bg-white object-cover shadow-2xl shadow-stone-900/10 dark:border-white/10 dark:bg-white/5" src="{{ asset('storage/'.$product->image) }}" alt="{{ $product->name }}">
            @else
                <div class="grid aspect-square w-full place-items-center rounded-[2.5rem] border border-stone-200 bg-gradient-to-br from-stone-100 via-white to-amber-100 text-9xl font-black text-[var(--storefront-brand)] shadow-2xl shadow-stone-900/10 dark:border-white/10 dark:from-stone-900 dark:via-stone-800 dark:to-stone-900">
                    {{ mb_substr($product->name, 0, 1) }}
                </div>
            @endif
        </div>

        <div>
            @if ($product->category)
                <a class="inline-flex rounded-full border border-stone-200 bg-white px-4 py-2 text-xs font-black uppercase tracking-[0.2em] text-stone-500 transition hover:border-[var(--storefront-brand)] hover:text-[var(--storefront-brand)] dark:border-white/10 dark:bg-white/10 dark:text-stone-300" href="{{ isset($previewSlug) ? route('storefront.preview.categories.show', [$previewSlug, $product->category->slug]) : route('storefront.categories.show', $product->category->slug) }}">{{ $product->category->name }}</a>
            @endif
            <h1 class="mt-5 text-4xl font-black tracking-[-0.06em] sm:text-6xl">{{ $product->name }}</h1>
            <div class="mt-5 text-4xl font-black text-[var(--storefront-brand)]">BDT {{ number_format($product->selling_price, 2) }}</div>
            <p class="mt-6 max-w-2xl text-lg leading-8 text-stone-600 dark:text-stone-300">
                {{ $product->description ?: 'Product details will be updated soon. Contact us for specifications, availability, and delivery details.' }}
            </p>

            <div class="mt-8 grid gap-3 sm:grid-cols-3">
                <div class="rounded-3xl border border-stone-200 bg-white p-5 dark:border-white/10 dark:bg-white/5">
                    <div class="text-xs font-black uppercase tracking-widest text-stone-500">Stock</div>
                    <div class="mt-1 text-2xl font-black">{{ (int) $product->stock }}</div>
                </div>
                <div class="rounded-3xl border border-stone-200 bg-white p-5 dark:border-white/10 dark:bg-white/5">
                    <div class="text-xs font-black uppercase tracking-widest text-stone-500">SKU</div>
                    <div class="mt-1 truncate text-base font-black">{{ $product->sku }}</div>
                </div>
                <div class="rounded-3xl border border-stone-200 bg-white p-5 dark:border-white/10 dark:bg-white/5">
                    <div class="text-xs font-black uppercase tracking-widest text-stone-500">Status</div>
                    <div class="mt-1 text-base font-black">Available</div>
                </div>
            </div>

            <div class="mt-8 flex flex-wrap gap-3">
                <form class="flex flex-wrap items-center gap-3" method="POST" action="{{ isset($previewSlug) ? route('storefront.preview.cart.add', [$previewSlug, $product->slug]) : route('storefront.cart.add', $product->slug) }}">
                    @csrf
                    <label class="sr-only" for="quantity">Quantity</label>
                    <input id="quantity" class="h-14 w-24 rounded-full border border-stone-300 bg-white px-5 text-center text-sm font-black text-stone-950 shadow-sm dark:border-white/15 dark:bg-white/10 dark:text-white" type="number" name="quantity" value="1" min="1" max="{{ max(1, (int) $product->stock) }}">
                    <button type="submit" class="inline-flex items-center rounded-full bg-[var(--storefront-brand)] px-7 py-4 text-sm font-black text-white shadow-xl shadow-stone-900/10 transition hover:-translate-y-0.5" @disabled($product->stock < 1)>
                        Add to cart
                    </button>
                </form>
                @if ($setting->whatsapp_number)
                    <a class="inline-flex items-center rounded-full border border-stone-300 bg-white px-7 py-4 text-sm font-black text-stone-950 transition hover:-translate-y-0.5 hover:border-stone-950 dark:border-white/15 dark:bg-white/10 dark:text-white dark:hover:border-white" href="https://wa.me/{{ preg_replace('/\D+/', '', $setting->whatsapp_number) }}?text={{ rawurlencode('I am interested in '.$product->name) }}" target="_blank" rel="noopener">
                        Ask on WhatsApp
                    </a>
                @endif
            </div>
        </div>
    </section>
@endsection
