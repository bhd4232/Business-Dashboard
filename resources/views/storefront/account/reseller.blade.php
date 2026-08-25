@extends('storefront.account.layout')

@php
    $accountRoute = static fn (string $name) => isset($previewSlug)
        ? route('storefront.preview.account.'.$name, $previewSlug)
        : route('storefront.account.'.$name);
@endphp

@section('account-content')
    <div>
        <p class="text-sm font-medium text-[var(--storefront-brand)]">Your Store</p>
        <h2 class="mt-1 text-2xl font-semibold tracking-tight">Manage your reseller store</h2>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Pick which products show on your own store page, and set its web address.</p>
    </div>

    <section class="mt-4 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900" aria-labelledby="store-url-heading">
        <h3 class="text-lg font-semibold tracking-tight" id="store-url-heading">Store address</h3>
        @if ($storeUrl)
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Your store is live at:</p>
            <a class="mt-1 block break-all text-sm font-semibold text-[var(--storefront-brand)] hover:underline" href="{{ $storeUrl }}" target="_blank" rel="noopener">{{ $storeUrl }}</a>
        @else
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Set a store URL below to go live.</p>
        @endif
        <form class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end" method="POST" action="{{ $accountRoute('reseller.slug') }}">
            @csrf
            @method('PATCH')
            <div class="flex-1">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300" for="reseller_slug">Store URL slug</label>
                <input class="mt-2 min-h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm outline-none transition focus:border-[var(--storefront-brand)] focus:ring-2 focus:ring-[color-mix(in_srgb,var(--storefront-brand)_20%,transparent)] dark:border-gray-700 dark:bg-gray-950" id="reseller_slug" name="reseller_slug" type="text" value="{{ old('reseller_slug', $customer->reseller_slug) }}" required>
                @error('reseller_slug') <p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>
            <button class="inline-flex min-h-11 items-center justify-center rounded-xl bg-[var(--storefront-brand)] px-6 text-sm font-semibold text-white transition hover:opacity-90" type="submit">Save</button>
        </form>
    </section>

    <section class="mt-4 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900" aria-labelledby="store-products-heading">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h3 class="text-lg font-semibold tracking-tight" id="store-products-heading">Products</h3>
            <form class="w-full sm:w-64" method="GET" action="{{ $accountRoute('reseller') }}">
                <input class="min-h-10 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm outline-none transition focus:border-[var(--storefront-brand)] focus:ring-2 focus:ring-[color-mix(in_srgb,var(--storefront-brand)_20%,transparent)] dark:border-gray-700 dark:bg-gray-950" type="search" name="search" value="{{ $search }}" placeholder="Search products...">
            </form>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($products as $product)
                @php $isPicked = in_array($product->id, $pickedProductIds, true); @endphp
                <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-800">
                    <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $product->name }}</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $product->sku }} &middot; {{ $company->currency ?: 'BDT' }} {{ \App\Support\MoneyFormatter::number((float) $product->sale_price) }}</p>
                    @php
                        $toggleUrl = isset($previewSlug)
                            ? route('storefront.preview.account.reseller.products.toggle', [$previewSlug, $product->id])
                            : route('storefront.account.reseller.products.toggle', $product->id);
                    @endphp
                    <form class="mt-3" method="POST" action="{{ $toggleUrl }}">
                        @csrf
                        <button
                            class="inline-flex min-h-9 w-full items-center justify-center rounded-lg px-4 text-xs font-semibold transition {{ $isPicked ? 'bg-red-50 text-red-700 hover:bg-red-100 dark:bg-red-950 dark:text-red-300' : 'bg-[var(--storefront-brand)] text-white hover:opacity-90' }}"
                            type="submit"
                        >
                            {{ $isPicked ? 'Remove from store' : 'Add to store' }}
                        </button>
                    </form>
                </div>
            @empty
                <p class="col-span-full text-sm text-gray-500 dark:text-gray-400">No products found.</p>
            @endforelse
        </div>

        <div class="mt-4">
            {{ $products->links() }}
        </div>
    </section>
@endsection
