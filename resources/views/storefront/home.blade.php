@extends('storefront.layout')

@section('content')
    @php
        $heroProduct = $products->first();
        $bannerImage = collect($setting->banner_images ?? [])->filter()->first();
        $bannerUrl = $bannerImage ? asset('storage/'.$bannerImage) : null;
        $heroHeading = $setting->hero_heading ?: 'Shop the latest from '.$company->name.'.';
        $heroSubheading = $setting->hero_subheading ?: 'Browse current products, order directly, and track purchases from one clean storefront.';
        $heroCta = $setting->hero_cta_label ?: 'Start shopping';
    @endphp

    <section class="border-b border-gray-200 dark:border-white/10">
        <div class="mx-auto grid w-full max-w-7xl gap-10 px-4 py-14 sm:px-6 lg:grid-cols-[1.05fr_0.95fr] lg:items-center lg:px-8 lg:py-20">
            <div>
                <p class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-[var(--storefront-brand)]">
                    <span class="h-1.5 w-1.5 rounded-full bg-[var(--storefront-brand)]"></span>
                    Official storefront
                </p>
                <h1 class="mt-4 max-w-xl text-4xl font-semibold tracking-tight text-gray-950 sm:text-5xl lg:text-6xl dark:text-white">
                    {{ $heroHeading }}
                </h1>
                <p class="mt-5 max-w-lg text-lg leading-8 text-gray-600 dark:text-gray-300">
                    {{ $heroSubheading }}
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a class="inline-flex items-center rounded-lg bg-gray-950 px-6 py-3 text-sm font-medium text-white transition hover:bg-[var(--storefront-brand)] dark:bg-white dark:text-gray-950" href="{{ isset($previewSlug) ? route('storefront.preview.products.index', $previewSlug) : route('storefront.products.index') }}">
                        {{ $heroCta }}
                    </a>
                    @if ($categories->isNotEmpty())
                        <a class="inline-flex items-center rounded-lg border border-gray-300 px-6 py-3 text-sm font-medium text-gray-900 transition hover:border-gray-950 dark:border-white/15 dark:text-white dark:hover:border-white" href="#collections">
                            Browse collections
                        </a>
                    @endif
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-white/5">
                @if ($bannerUrl)
                    <img class="aspect-[4/3] w-full object-cover" src="{{ $bannerUrl }}" alt="{{ $company->name }} storefront banner">
                @elseif ($heroProduct?->image)
                    <img class="aspect-[4/3] w-full object-cover" src="{{ asset('storage/'.$heroProduct->image) }}" alt="{{ $heroProduct->name }}">
                @else
                    <div class="grid aspect-[4/3] place-items-center text-7xl font-semibold text-[var(--storefront-brand)]">
                        {{ mb_substr($heroProduct?->name ?? $company->name, 0, 1) }}
                    </div>
                @endif
            </div>
        </div>
    </section>

    @if ($categories->isNotEmpty())
        <section id="collections" class="mx-auto w-full max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
            <div class="mb-8 flex items-end justify-between gap-5">
                <h2 class="text-2xl font-semibold tracking-tight sm:text-3xl">Shop by category</h2>
                <a class="hidden text-sm font-medium text-gray-500 hover:text-gray-950 sm:inline dark:hover:text-white" href="{{ isset($previewSlug) ? route('storefront.preview.products.index', $previewSlug) : route('storefront.products.index') }}">View all products</a>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($categories as $category)
                    <a class="group rounded-xl border border-gray-200 bg-white p-6 transition hover:border-[var(--storefront-brand)] hover:shadow-sm dark:border-white/10 dark:bg-white/5" href="{{ isset($previewSlug) ? route('storefront.preview.categories.show', [$previewSlug, $category->slug]) : route('storefront.categories.show', $category->slug) }}">
                        <div class="mb-6 grid h-11 w-11 place-items-center rounded-lg bg-gray-100 text-lg font-semibold text-gray-700 transition group-hover:bg-[var(--storefront-brand)] group-hover:text-white dark:bg-white/10 dark:text-gray-200">
                            {{ mb_substr($category->name, 0, 1) }}
                        </div>
                        <div class="text-base font-semibold">{{ $category->name }}</div>
                        <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Explore collection</div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <section class="mx-auto w-full max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
        <div class="mb-8 flex items-end justify-between gap-5">
            <h2 class="text-2xl font-semibold tracking-tight sm:text-3xl">Featured products</h2>
            <a class="text-sm font-medium text-gray-500 hover:text-gray-950 dark:hover:text-white" href="{{ isset($previewSlug) ? route('storefront.preview.products.index', $previewSlug) : route('storefront.products.index') }}">View all</a>
        </div>

        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @forelse ($products as $product)
                @include('storefront.partials.product-card', ['product' => $product])
            @empty
                <div class="rounded-xl border border-dashed border-gray-300 p-10 text-center text-gray-500 dark:border-white/15">
                    No available products yet.
                </div>
            @endforelse
        </div>
    </section>
@endsection
