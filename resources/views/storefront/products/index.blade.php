@extends('storefront.layout')

@section('content')
    <section class="border-b border-stone-200 bg-white dark:border-white/10 dark:bg-stone-950">
        <div class="mx-auto w-full max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-sm font-black uppercase tracking-[0.22em] text-[var(--storefront-brand)]">{{ $category?->name ?? 'Catalog' }}</p>
                    <h1 class="mt-3 text-4xl font-black tracking-[-0.05em] sm:text-6xl">{{ $category?->name ?? 'All products' }}</h1>
                    <p class="mt-4 max-w-2xl text-lg leading-8 text-stone-600 dark:text-stone-300">
                        Explore products currently available from {{ $company->name }}.
                    </p>
                </div>
                <div class="rounded-full border border-stone-200 bg-stone-50 px-5 py-3 text-sm font-black text-stone-600 dark:border-white/10 dark:bg-white/10 dark:text-stone-300">
                    {{ $products->total() }} products
                </div>
            </div>
        </div>
    </section>

    <section class="mx-auto w-full max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @forelse ($products as $product)
                @include('storefront.partials.product-card', ['product' => $product])
            @empty
                <div class="rounded-3xl border border-dashed border-stone-300 p-10 text-center text-stone-500 dark:border-white/15">
                    No products found.
                </div>
            @endforelse
        </div>

        <div class="mt-10">
            {{ $products->links() }}
        </div>
    </section>
@endsection
