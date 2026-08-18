@php
    $galleryImages = $offer->items
        ->flatMap(fn ($item) => array_filter([$item->product->image, ...(array) ($item->product->gallery_images ?? [])]))
        ->unique()
        ->map(fn (string $path) => \App\Support\CompanyMedia::publicUrl($path, $company))
        ->filter()
        ->take(8);
@endphp

@if ($galleryImages->isNotEmpty())
    <section class="border-b border-gray-200 py-10 dark:border-white/10">
        <div class="mx-auto w-full max-w-5xl px-4 sm:px-5 lg:px-6">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                @foreach ($galleryImages as $imageUrl)
                    <img class="aspect-square w-full rounded-lg object-cover" src="{{ $imageUrl }}" alt="{{ $offer->title }}" loading="lazy" decoding="async">
                @endforeach
            </div>
        </div>
    </section>
@endif
