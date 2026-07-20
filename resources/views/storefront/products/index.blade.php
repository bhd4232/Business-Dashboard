@extends('storefront.layout')

@php
    $sortOptions = [
        '' => 'Newest',
        'price_asc' => 'Price: Low to high',
        'price_desc' => 'Price: High to low',
    ];
    $baseProductsUrl = isset($previewSlug) ? route('storefront.preview.products.index', $previewSlug) : route('storefront.products.index');
@endphp

@section('content')
    <section class="border-b border-gray-200 dark:border-white/10">
        <div class="mx-auto w-full max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-[var(--storefront-brand)]">{{ ($search ?? '') !== '' ? 'Search' : ($category?->name ?? 'Catalog') }}</p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl">
                        @if (($search ?? '') !== '')
                            Results for &ldquo;{{ $search }}&rdquo;
                        @else
                            {{ $category?->name ?? 'All products' }}
                        @endif
                    </h1>
                </div>
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ $products->total() }} {{ str('product')->plural($products->total()) }}</div>
            </div>

            @if ($categories->isNotEmpty())
                <div class="mt-6 flex flex-wrap gap-2">
                    <a class="rounded-full border px-4 py-1.5 text-sm font-medium transition {{ ! $category ? 'border-[var(--storefront-brand)] bg-[var(--storefront-brand)] text-white' : 'border-gray-200 text-gray-600 hover:border-gray-400 dark:border-white/10 dark:text-gray-300' }}" href="{{ $baseProductsUrl }}" @if (! $category) aria-current="page" @endif>
                        All
                    </a>
                    @foreach ($categories as $chip)
                        @php
                            $chipUrl = isset($previewSlug) ? route('storefront.preview.categories.show', [$previewSlug, $chip->slug]) : route('storefront.categories.show', $chip->slug);
                        @endphp
                        <a class="rounded-full border px-4 py-1.5 text-sm font-medium transition {{ $category?->id === $chip->id ? 'border-[var(--storefront-brand)] bg-[var(--storefront-brand)] text-white' : 'border-gray-200 text-gray-600 hover:border-gray-400 dark:border-white/10 dark:text-gray-300' }}" href="{{ $chipUrl }}" @if ($category?->id === $chip->id) aria-current="page" @endif>
                            {{ $chip->name }}
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <section class="mx-auto w-full max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        <form class="mb-6 flex justify-end" method="GET">
            @if (($search ?? '') !== '')
                <input type="hidden" name="q" value="{{ $search }}">
            @endif
            <label class="sr-only" for="sort">Sort products</label>
            <select id="sort" name="sort" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 dark:border-white/10 dark:bg-white/5 dark:text-gray-200" onchange="this.form.submit()">
                @foreach ($sortOptions as $value => $label)
                    <option value="{{ $value }}" @selected($sort === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </form>

        <div class="storefront-catalog-grid grid grid-cols-2 gap-3 sm:gap-5 lg:grid-cols-4">
            @forelse ($products as $product)
                @include('storefront.partials.product-card', ['product' => $product])
            @empty
                <div class="col-span-full rounded-xl border border-dashed border-gray-300 p-10 text-center text-gray-500 dark:border-white/15">
                    @if (($search ?? '') !== '')
                        No products match &ldquo;{{ $search }}&rdquo;. Try a different search term.
                    @else
                        No products found.
                    @endif
                </div>
            @endforelse
        </div>

        <div class="mt-10">
            {{ $products->links() }}
        </div>
    </section>
@endsection
