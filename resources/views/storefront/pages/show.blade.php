@extends('storefront.layout')

@section('content')
    <section class="mx-auto w-full max-w-4xl px-4 py-12 sm:px-6 lg:px-8">
        <div class="rounded-[2rem] border border-stone-200 bg-white p-6 shadow-sm sm:p-10 dark:border-white/10 dark:bg-white/5">
            <p class="text-sm font-black uppercase tracking-[0.22em] text-[var(--storefront-brand)]">{{ $company->name }}</p>
            <h1 class="mt-3 text-4xl font-black tracking-[-0.05em] sm:text-6xl">{{ $page->title }}</h1>

            @if ($page->excerpt)
                <p class="mt-5 text-lg leading-8 text-stone-600 dark:text-stone-300">{{ $page->excerpt }}</p>
            @endif

            <div class="mt-8 max-w-none space-y-5 text-base leading-8 text-stone-700 dark:text-stone-200">
                @foreach (preg_split("/\r\n|\n|\r/", trim($page->content)) as $paragraph)
                    @if (trim($paragraph) !== '')
                        <p>{{ $paragraph }}</p>
                    @endif
                @endforeach
            </div>
        </div>
    </section>
@endsection
