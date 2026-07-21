@extends('storefront.layout')

@php
    use Filament\Forms\Components\RichEditor\RichContentRenderer;

    $homeUrl = isset($previewSlug) ? route('storefront.preview.show', $previewSlug) : route('marketing.home');
    $contactUrl = isset($previewSlug) ? route('storefront.preview.contact', $previewSlug) : route('storefront.contact');
    $coverImageUrl = \App\Support\CompanyMedia::publicUrl($page->cover_image, $company);
    $isHtmlContent = str_contains((string) $page->content, '<');
@endphp

@section('content')
    <nav class="mx-auto w-full max-w-4xl px-4 pt-6 text-sm text-gray-500 sm:px-6 lg:px-8 dark:text-gray-400" aria-label="Breadcrumb">
        <a class="hover:text-gray-900 dark:hover:text-white" href="{{ $homeUrl }}">Home</a>
        <span class="mx-2">/</span>
        <span class="text-gray-900 dark:text-white">{{ $page->title }}</span>
    </nav>

    <article class="mx-auto w-full max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
        @if ($coverImageUrl)
            <img class="mb-8 aspect-[21/9] w-full rounded-2xl object-cover" src="{{ $coverImageUrl }}" alt="{{ $page->title }}" width="1680" height="720" fetchpriority="high">
        @endif

        <p class="text-xs font-semibold uppercase tracking-wider text-[var(--storefront-brand)]">{{ $company->name }}</p>
        <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">{{ $page->title }}</h1>
        <p class="mt-2 text-xs text-gray-400 dark:text-gray-500">Last updated {{ $page->updated_at->format('d M, Y') }}</p>

        @if ($page->excerpt)
            <p class="mt-5 text-lg leading-8 text-gray-600 dark:text-gray-300">{{ $page->excerpt }}</p>
        @endif

        <div class="storefront-richtext mt-8 text-base leading-8 text-gray-700 dark:text-gray-200">
            @if ($isHtmlContent)
                {{ RichContentRenderer::make($page->content) }}
            @else
                @foreach (preg_split("/\r\n|\n|\r/", trim($page->content)) as $paragraph)
                    @if (trim($paragraph) !== '')
                        <p>{{ $paragraph }}</p>
                    @endif
                @endforeach
            @endif
        </div>

        <div class="mt-12 flex flex-col items-center gap-3 rounded-2xl border border-gray-200 bg-gray-50 px-6 py-8 text-center dark:border-white/10 dark:bg-white/5">
            <div class="text-base font-semibold text-gray-900 dark:text-white">Still have questions?</div>
            <p class="text-sm text-gray-500 dark:text-gray-400">Can&rsquo;t find what you&rsquo;re looking for? Our team is happy to help.</p>
            <a class="inline-flex items-center rounded-lg bg-[var(--storefront-brand)] px-6 py-2.5 text-sm font-medium text-white transition hover:opacity-90" href="{{ $contactUrl }}">
                Contact us
            </a>
        </div>
    </article>
@endsection
