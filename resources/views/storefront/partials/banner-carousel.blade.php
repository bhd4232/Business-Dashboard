@php
    $banners = $banners->values();
@endphp

<div
    class="{{ $class ?? '' }} relative aspect-[4/3] w-full overflow-hidden"
    x-data="{
        count: {{ $banners->count() }},
        active: 0,
        timer: null,
        start() {
            if (this.count < 2 || window.matchMedia('(prefers-reduced-motion: reduce)').matches) { return; }
            this.timer = setInterval(() => { this.active = (this.active + 1) % this.count; }, 5000);
        },
    }"
    x-init="start()"
>
    @foreach ($banners as $index => $banner)
        @php
            $bannerHref = $bannerLink($banner['product_id'] ?? null);
            $bannerTag = $bannerHref ? 'a' : 'div';
        @endphp
        <{{ $bannerTag }}
            @if ($bannerHref) href="{{ $bannerHref }}" @endif
            x-show="active === {{ $index }}"
            x-transition:enter="transition ease-out duration-500"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-cloak
            class="absolute inset-0 block"
        >
            <img
                class="h-full w-full object-cover"
                src="{{ asset('storage/'.$banner['image']) }}"
                alt="{{ $company->name }} storefront banner"
                @if ($index === 0) fetchpriority="high" loading="eager" @else loading="lazy" @endif
            >
        </{{ $bannerTag }}>
    @endforeach

    @if ($banners->count() > 1)
        <div class="absolute inset-x-0 bottom-3 flex justify-center gap-2">
            @foreach ($banners as $index => $banner)
                <button
                    type="button"
                    @click="active = {{ $index }}"
                    :class="active === {{ $index }} ? 'w-5 bg-white' : 'w-1.5 bg-white/50'"
                    class="h-1.5 rounded-full transition-all"
                    aria-label="Go to banner {{ $index + 1 }}"
                ></button>
            @endforeach
        </div>
    @endif
</div>
