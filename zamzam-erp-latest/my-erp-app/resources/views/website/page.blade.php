@extends('website.layout', [
    'title' => $page->seo_title ?: $page->title,
    'description' => $page->seo_description ?: $page->excerpt,
    'canonical' => route('website.pages.show', $page->slug),
    'ogTitle' => $page->seo_title ?: $page->title,
    'ogDescription' => $page->seo_description ?: $page->excerpt,
    'ogImage' => \App\Http\Controllers\WebsiteController::absoluteMediaUrl($page->og_image ?: $settings?->og_image ?: $settings?->logo),
    'ogType' => 'article',
])

@section('content')
    <article class="page-content">
        <h1>{{ $page->title }}</h1>
        @if($page->excerpt)
            <p class="page-body">{{ $page->excerpt }}</p>
        @endif
        <div class="page-body">
            {!! $page->content !!}
        </div>
    </article>
@endsection
