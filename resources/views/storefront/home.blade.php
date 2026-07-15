@extends('storefront.layout')

@section('content')
    @php
        $heroProduct = $products->first();
        $bannerImage = collect($setting->banner_images ?? [])->filter()->first();
        $bannerUrl = $bannerImage ? asset('storage/'.$bannerImage) : null;
        $bannerMobileUrl = $setting->banner_image_mobile ? asset('storage/'.$setting->banner_image_mobile) : null;
        $heroHeading = $setting->hero_heading ?: 'Shop the latest from '.$company->name.'.';
        $heroSubheading = $setting->hero_subheading ?: 'Browse current products, order directly, and track purchases from one clean storefront.';
        $heroCta = $setting->hero_cta_label ?: 'Start shopping';
    @endphp

    @if (($slides ?? collect())->isNotEmpty())
        <section
            class="relative overflow-hidden border-b border-gray-200 dark:border-white/10"
            x-data="{
                slides: {{ $slides->count() }},
                active: 0,
                timer: null,
                start() {
                    if (this.slides < 2 || window.matchMedia('(prefers-reduced-motion: reduce)').matches) { return; }
                    this.timer = setInterval(() => { this.active = (this.active + 1) % this.slides; }, 5000);
                },
                go(index) { this.active = (index + this.slides) % this.slides; }
            }"
            x-init="start()"
        >
            <div class="relative aspect-[16/9] w-full sm:aspect-[21/9]">
                @foreach ($slides as $index => $slide)
                    <div
                        x-show="active === {{ $index }}"
                        x-transition:enter="transition ease-out duration-500"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        x-cloak
                        class="absolute inset-0"
                    >
                        <picture>
                            @if ($slide->image_mobile)
                                <source media="(max-width: 639px)" srcset="{{ asset('storage/'.$slide->image_mobile) }}">
                            @endif
                            <img
                                class="h-full w-full object-cover"
                                src="{{ asset('storage/'.$slide->image) }}"
                                alt="{{ $slide->heading ?: $company->name }}"
                                @if ($index === 0) fetchpriority="high" loading="eager" @else loading="lazy" @endif
                            >
                        </picture>
                        @if ($slide->heading || $slide->cta_label)
                            <div class="absolute inset-0 flex items-center bg-gradient-to-r from-black/50 via-black/10 to-transparent">
                                <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
                                    <div class="max-w-lg text-white">
                                        @if ($slide->heading)
                                            <h2 class="text-2xl font-semibold tracking-tight sm:text-4xl">{{ $slide->heading }}</h2>
                                        @endif
                                        @if ($slide->subheading)
                                            <p class="mt-2 text-sm text-white/90 sm:text-base">{{ $slide->subheading }}</p>
                                        @endif
                                        @if ($slide->cta_label)
                                            <a class="mt-5 inline-flex items-center rounded-lg bg-white px-5 py-2.5 text-sm font-medium text-gray-950 transition hover:bg-white/90" href="{{ $slide->cta_url ?: (isset($previewSlug) ? route('storefront.preview.products.index', $previewSlug) : route('storefront.products.index')) }}">
                                                {{ $slide->cta_label }}
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            @if ($slides->count() > 1)
                <div class="absolute inset-x-0 bottom-4 flex justify-center gap-2">
                    @foreach ($slides as $index => $slide)
                        <button
                            type="button"
                            @click="go({{ $index }})"
                            :class="active === {{ $index }} ? 'w-6 bg-white' : 'w-2 bg-white/50'"
                            class="h-2 rounded-full transition-all"
                            aria-label="Go to slide {{ $index + 1 }}"
                        ></button>
                    @endforeach
                </div>
            @endif
        </section>
    @else
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
                        <picture>
                            @if ($bannerMobileUrl)
                                <source media="(max-width: 639px)" srcset="{{ $bannerMobileUrl }}">
                            @endif
                            <img class="aspect-[4/3] w-full object-cover" src="{{ $bannerUrl }}" alt="{{ $company->name }} storefront banner">
                        </picture>
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
    @endif

    @if ($setting->hasActiveOffer())
        <section
            class="border-b border-gray-200 bg-gray-950 text-white dark:border-white/10"
            x-data="{
                endsAt: new Date('{{ $setting->offer_ends_at->toIso8601String() }}').getTime(),
                remaining: { d: 0, h: 0, m: 0, s: 0 },
                tick() {
                    const diff = Math.max(0, this.endsAt - Date.now());
                    this.remaining = {
                        d: Math.floor(diff / 86400000),
                        h: Math.floor((diff / 3600000) % 24),
                        m: Math.floor((diff / 60000) % 60),
                        s: Math.floor((diff / 1000) % 60),
                    };
                }
            }"
            x-init="tick(); setInterval(() => tick(), 1000)"
        >
            <div class="mx-auto flex w-full max-w-7xl flex-wrap items-center justify-center gap-3 px-4 py-3 text-sm sm:justify-between sm:px-6 lg:px-8">
                <div class="font-semibold">
                    {{ $setting->offer_title }}
                    @if ($setting->offer_discount_percent)
                        <span class="text-[var(--storefront-brand)]">— up to {{ $setting->offer_discount_percent }}% off</span>
                    @endif
                </div>
                <div class="flex items-center gap-1.5 font-mono text-xs" aria-label="Offer ends in">
                    <template x-if="remaining.d > 0">
                        <span class="rounded bg-white/10 px-2 py-1" x-text="remaining.d + 'd'"></span>
                    </template>
                    <span class="rounded bg-white/10 px-2 py-1" x-text="String(remaining.h).padStart(2, '0') + 'h'"></span>
                    <span class="rounded bg-white/10 px-2 py-1" x-text="String(remaining.m).padStart(2, '0') + 'm'"></span>
                    <span class="rounded bg-white/10 px-2 py-1" x-text="String(remaining.s).padStart(2, '0') + 's'"></span>
                </div>
            </div>
        </section>
    @endif

    @if ($setting->trust_strip_delivery || $setting->trust_strip_return || $setting->trust_strip_payment)
        <section class="border-b border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-white/[0.02]">
            <div class="mx-auto grid w-full max-w-7xl gap-4 px-4 py-4 sm:grid-cols-3 sm:px-6 lg:px-8">
                @foreach ([$setting->trust_strip_delivery, $setting->trust_strip_return, $setting->trust_strip_payment] as $item)
                    @if ($item)
                        <div class="flex items-center justify-center gap-2 text-center text-xs font-medium text-gray-600 sm:justify-start sm:text-left dark:text-gray-300">
                            <svg class="h-4 w-4 shrink-0 text-[var(--storefront-brand)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                            {{ $item }}
                        </div>
                    @endif
                @endforeach
            </div>
        </section>
    @endif

    <section class="border-b border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-white/[0.02]">
        <div class="mx-auto grid w-full max-w-7xl gap-6 px-4 py-12 sm:px-6 sm:grid-cols-2 lg:grid-cols-4 lg:px-8">
            @foreach ([
                ['1', 'Choose a product', 'Browse the catalog and pick what you need.'],
                ['2', 'Add to cart', 'Set the quantity and add it to your cart.'],
                ['3', 'Confirm order', 'Fill in your delivery details and confirm.'],
                ['4', 'Receive delivery', 'Track your order until it reaches you.'],
            ] as [$step, $stepTitle, $stepDescription])
                <div class="flex items-start gap-3">
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-gray-950 text-sm font-semibold text-white dark:bg-white dark:text-gray-950">{{ $step }}</span>
                    <div>
                        <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ $stepTitle }}</div>
                        <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $stepDescription }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    @if ($categories->isNotEmpty())
        <section id="collections" class="mx-auto w-full max-w-7xl px-4 py-14 sm:px-6 lg:px-8" x-reveal>
            <div class="mb-8 flex items-end justify-between gap-5">
                <h2 class="text-2xl font-semibold tracking-tight sm:text-3xl">Shop by category</h2>
                <a class="hidden text-sm font-medium text-gray-500 hover:text-gray-950 sm:inline dark:hover:text-white" href="{{ isset($previewSlug) ? route('storefront.preview.products.index', $previewSlug) : route('storefront.products.index') }}">View all products</a>
            </div>
            <div class="-mx-4 flex snap-x snap-mandatory gap-4 overflow-x-auto px-4 pb-2 sm:mx-0 sm:grid sm:grid-cols-2 sm:overflow-visible sm:px-0 lg:grid-cols-4">
                @foreach ($categories as $category)
                    <a class="group w-36 shrink-0 snap-start overflow-hidden rounded-xl border border-gray-200 bg-white transition hover:border-[var(--storefront-brand)] hover:shadow-sm sm:w-auto dark:border-white/10 dark:bg-white/5" href="{{ isset($previewSlug) ? route('storefront.preview.categories.show', [$previewSlug, $category->slug]) : route('storefront.categories.show', $category->slug) }}">
                        <div class="aspect-square overflow-hidden bg-gray-100 dark:bg-white/10">
                            @if ($category->image)
                                <img class="h-full w-full object-cover transition duration-300 group-hover:scale-105" src="{{ asset('storage/'.$category->image) }}" alt="{{ $category->name }}" loading="lazy" decoding="async">
                            @else
                                <div class="grid h-full w-full place-items-center text-2xl font-semibold text-gray-700 transition group-hover:text-[var(--storefront-brand)] dark:text-gray-200">
                                    {{ mb_substr($category->name, 0, 1) }}
                                </div>
                            @endif
                        </div>
                        <div class="p-4">
                            <div class="truncate text-base font-semibold">{{ $category->name }}</div>
                            <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Explore collection</div>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <section class="mx-auto w-full max-w-7xl px-4 py-14 sm:px-6 lg:px-8" x-reveal>
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

    @foreach ($carousels ?? [] as $carousel)
        <section class="mx-auto w-full max-w-7xl px-4 pb-14 sm:px-6 lg:px-8" x-reveal>
            <div class="mb-8 flex items-end justify-between gap-5">
                <div>
                    <h2 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ $carousel->title }}</h2>
                    @if ($carousel->subtitle)
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $carousel->subtitle }}</p>
                    @endif
                </div>
                <a class="hidden text-sm font-medium text-gray-500 hover:text-gray-950 sm:inline dark:hover:text-white" href="{{ isset($previewSlug) ? route('storefront.preview.products.index', $previewSlug) : route('storefront.products.index') }}">View all</a>
            </div>
            <div class="-mx-4 overflow-x-auto px-4 pb-2 sm:mx-0 sm:px-0">
                <div class="flex snap-x snap-mandatory gap-5">
                    @foreach ($carousel->products as $product)
                        <div class="w-64 shrink-0 snap-start sm:w-72">
                            @include('storefront.partials.product-card', ['product' => $product])
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endforeach
@endsection
