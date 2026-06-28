@extends('storefront.layout')

@section('content')
    <section class="relative overflow-hidden">
        <div class="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_top_left,rgba(245,158,11,0.22),transparent_34%),linear-gradient(135deg,#fff7ed,transparent_45%)] dark:bg-[radial-gradient(circle_at_top_left,rgba(245,158,11,0.24),transparent_34%),linear-gradient(135deg,#1c1917,transparent_45%)]"></div>
        <div class="mx-auto grid w-full max-w-7xl gap-10 px-4 py-14 sm:px-6 lg:grid-cols-[1.05fr_0.95fr] lg:px-8 lg:py-20">
            <div class="flex flex-col justify-center">
                <div class="mb-5 inline-flex w-fit items-center gap-2 rounded-full border border-stone-200 bg-white px-4 py-2 text-xs font-black uppercase tracking-[0.22em] text-stone-600 shadow-sm dark:border-white/10 dark:bg-white/10 dark:text-stone-200">
                    <span class="h-2 w-2 rounded-full bg-[var(--storefront-brand)]"></span>
                    Official storefront
                </div>
                <h1 class="max-w-4xl text-5xl font-black tracking-[-0.07em] text-stone-950 sm:text-6xl lg:text-7xl dark:text-white">
                    Shop the latest from {{ $company->name }}.
                </h1>
                <p class="mt-6 max-w-2xl text-lg leading-8 text-stone-600 dark:text-stone-300">
                    A Shopify-inspired storefront connected directly to ZamZam ERP products, stock, pricing, categories, and company branding.
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a class="inline-flex items-center rounded-full bg-[var(--storefront-brand)] px-6 py-3 text-sm font-black text-white shadow-xl shadow-stone-900/10 transition hover:-translate-y-0.5" href="{{ isset($previewSlug) ? route('storefront.preview.products.index', $previewSlug) : route('storefront.products.index') }}">
                        Start shopping
                    </a>
                    <a class="inline-flex items-center rounded-full border border-stone-300 bg-white px-6 py-3 text-sm font-black text-stone-950 transition hover:-translate-y-0.5 hover:border-stone-950 dark:border-white/15 dark:bg-white/10 dark:text-white dark:hover:border-white" href="#collections">
                        Browse collections
                    </a>
                </div>
                <div class="mt-10 grid max-w-xl grid-cols-3 gap-3 text-center">
                    <div class="rounded-3xl border border-stone-200 bg-white/80 p-4 shadow-sm dark:border-white/10 dark:bg-white/10">
                        <div class="text-2xl font-black">{{ $products->count() }}+</div>
                        <div class="mt-1 text-xs font-bold uppercase tracking-widest text-stone-500">Products</div>
                    </div>
                    <div class="rounded-3xl border border-stone-200 bg-white/80 p-4 shadow-sm dark:border-white/10 dark:bg-white/10">
                        <div class="text-2xl font-black">{{ $categories->count() }}+</div>
                        <div class="mt-1 text-xs font-bold uppercase tracking-widest text-stone-500">Collections</div>
                    </div>
                    <div class="rounded-3xl border border-stone-200 bg-white/80 p-4 shadow-sm dark:border-white/10 dark:bg-white/10">
                        <div class="text-2xl font-black">ERP</div>
                        <div class="mt-1 text-xs font-bold uppercase tracking-widest text-stone-500">Powered</div>
                    </div>
                </div>
            </div>

            <div class="relative min-h-[480px]">
                <div class="absolute right-0 top-0 h-72 w-72 rounded-full bg-[var(--storefront-brand)] opacity-20 blur-3xl"></div>
                <div class="relative rounded-[2.5rem] border border-white/60 bg-white/80 p-4 shadow-2xl shadow-stone-900/10 backdrop-blur dark:border-white/10 dark:bg-white/10">
                    @php($heroProduct = $products->first())
                    <div class="overflow-hidden rounded-[2rem] bg-stone-100 dark:bg-stone-900">
                        @if ($heroProduct?->image)
                            <img class="h-[380px] w-full object-cover" src="{{ asset('storage/'.$heroProduct->image) }}" alt="{{ $heroProduct->name }}">
                        @else
                            <div class="grid h-[380px] place-items-center bg-gradient-to-br from-stone-100 via-white to-amber-100 text-8xl font-black text-[var(--storefront-brand)] dark:from-stone-900 dark:via-stone-800 dark:to-stone-900">
                                {{ mb_substr($heroProduct?->name ?? $company->name, 0, 1) }}
                            </div>
                        @endif
                    </div>
                    <div class="mt-4 flex items-center justify-between gap-4 rounded-3xl bg-stone-950 p-5 text-white dark:bg-white dark:text-stone-950">
                        <div>
                            <div class="text-xs font-black uppercase tracking-[0.2em] opacity-70">Featured pick</div>
                            <div class="mt-1 text-xl font-black">{{ $heroProduct?->name ?? 'Featured product' }}</div>
                        </div>
                        <div class="rounded-full bg-[var(--storefront-brand)] px-4 py-2 text-sm font-black text-white">
                            BDT {{ number_format((float) ($heroProduct?->selling_price ?? 0), 2) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    @if ($categories->isNotEmpty())
        <section id="collections" class="mx-auto w-full max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
            <div class="mb-8 flex items-end justify-between gap-5">
                <div>
                    <p class="text-sm font-black uppercase tracking-[0.22em] text-[var(--storefront-brand)]">Collections</p>
                    <h2 class="mt-2 text-3xl font-black tracking-tight sm:text-4xl">Shop by category</h2>
                </div>
                <a class="hidden text-sm font-black text-stone-500 hover:text-stone-950 sm:inline dark:hover:text-white" href="{{ isset($previewSlug) ? route('storefront.preview.products.index', $previewSlug) : route('storefront.products.index') }}">View all products</a>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($categories as $category)
                    <a class="group rounded-[2rem] border border-stone-200 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:border-[var(--storefront-brand)] hover:shadow-xl hover:shadow-stone-900/10 dark:border-white/10 dark:bg-white/5" href="{{ isset($previewSlug) ? route('storefront.preview.categories.show', [$previewSlug, $category->slug]) : route('storefront.categories.show', $category->slug) }}">
                        <div class="mb-8 grid h-14 w-14 place-items-center rounded-2xl bg-stone-100 text-2xl font-black text-[var(--storefront-brand)] transition group-hover:bg-[var(--storefront-brand)] group-hover:text-white dark:bg-white/10">
                            {{ mb_substr($category->name, 0, 1) }}
                        </div>
                        <div class="text-xl font-black">{{ $category->name }}</div>
                        <div class="mt-2 text-sm font-semibold text-stone-500 dark:text-stone-400">Explore collection</div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <section class="mx-auto w-full max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
        <div class="mb-8 flex items-end justify-between gap-5">
            <div>
                <p class="text-sm font-black uppercase tracking-[0.22em] text-[var(--storefront-brand)]">Fresh arrivals</p>
                <h2 class="mt-2 text-3xl font-black tracking-tight sm:text-4xl">Featured products</h2>
            </div>
            <a class="text-sm font-black text-stone-500 hover:text-stone-950 dark:hover:text-white" href="{{ isset($previewSlug) ? route('storefront.preview.products.index', $previewSlug) : route('storefront.products.index') }}">View all</a>
        </div>

        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @forelse ($products as $product)
                @include('storefront.partials.product-card', ['product' => $product])
            @empty
                <div class="rounded-3xl border border-dashed border-stone-300 p-10 text-center text-stone-500 dark:border-white/15">
                    No available products yet.
                </div>
            @endforelse
        </div>
    </section>
@endsection
