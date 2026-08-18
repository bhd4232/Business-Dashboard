@php
    use Filament\Forms\Components\RichEditor\RichContentRenderer;
@endphp

@if (! empty($data['html']))
    <section class="border-b border-gray-200 py-10 dark:border-white/10">
        <div class="storefront-richtext mx-auto w-full max-w-3xl px-4 text-[15px] leading-7 text-gray-700 sm:px-5 lg:px-6 dark:text-gray-200">
            {{ RichContentRenderer::make($data['html']) }}
        </div>
    </section>
@endif
