@extends('storefront.layout')

@section('content')
    <section class="mx-auto w-full max-w-4xl px-4 py-12 sm:px-6 lg:px-8">
        <div class="rounded-xl border border-gray-200 bg-white p-6 sm:p-10 dark:border-white/10 dark:bg-white/5">
            <p class="text-xs font-semibold uppercase tracking-wider text-[var(--storefront-brand)]">{{ $company->name }}</p>
            <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">{{ $page->title }}</h1>

            @if ($page->excerpt)
                <p class="mt-4 text-lg leading-8 text-gray-600 dark:text-gray-300">{{ $page->excerpt }}</p>
            @endif

            <div class="mt-8 max-w-none space-y-4 text-base leading-8 text-gray-700 dark:text-gray-200">
                @foreach (preg_split("/\r\n|\n|\r/", trim($page->content)) as $paragraph)
                    @if (trim($paragraph) !== '')
                        <p>{{ $paragraph }}</p>
                    @endif
                @endforeach
            </div>
        </div>
    </section>
@endsection
