@php
    $heroImageUrl = \App\Support\CompanyMedia::publicUrl($offer->cover_image, $company);
@endphp

<section class="relative overflow-hidden border-b border-gray-200 dark:border-white/10">
    @if ($heroImageUrl)
        <img class="absolute inset-0 h-full w-full object-cover" src="{{ $heroImageUrl }}" alt="{{ $offer->title }}" fetchpriority="high">
        <div class="absolute inset-0 bg-gray-950/55"></div>
    @endif
    <div class="relative mx-auto flex w-full max-w-5xl flex-col items-center px-4 py-16 text-center sm:px-5 lg:px-6 {{ $heroImageUrl ? 'text-white' : '' }}">
        <h1 class="text-3xl font-semibold tracking-tight sm:text-5xl">{{ $data['headline'] ?? $offer->title }}</h1>
        @if (! empty($data['subheadline']))
            <p class="mt-4 max-w-2xl text-base leading-7 {{ $heroImageUrl ? 'text-white/90' : 'text-gray-600 dark:text-gray-300' }} sm:text-lg">{{ $data['subheadline'] }}</p>
        @endif
        <a href="#offer-checkout" class="mt-8 inline-flex items-center rounded-lg bg-[var(--storefront-brand)] px-8 py-3 text-sm font-semibold text-white transition hover:opacity-90 sm:text-base">
            {{ $data['cta_label'] ?? 'এখনই অর্ডার করুন' }}
        </a>
    </div>
</section>
