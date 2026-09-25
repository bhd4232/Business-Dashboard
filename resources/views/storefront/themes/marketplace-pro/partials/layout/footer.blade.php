{{--
    Marketplace Pro footer: the same admin-managed Footer Builder blocks as
    the default footer (Storefront Settings → Footer), on the theme's dark
    navy surface. The `dark` class makes every footer block use its
    existing dark-mode styles, so no block needs a theme-specific copy.
--}}
@php
    $footerBlocks = collect($setting->footerBlocks());
    $footerBottomBar = $footerBlocks->first(fn (array $block): bool => ($block['type'] ?? null) === 'bottom_bar');
    $footerGridBlocks = $footerBlocks->reject(fn (array $block): bool => ($block['type'] ?? null) === 'bottom_bar');
@endphp

<footer class="storefront-footer mp-footer dark mb-16 mt-12 lg:mb-0">
    <a class="block bg-white/10 py-3 text-center text-sm font-semibold text-white transition hover:bg-white/15" href="#main-content">{{ __('Back to top') }}</a>

    @if ($sisterCompanies->isNotEmpty())
        <div class="border-b border-white/10">
            <div class="mp-shell py-5">
                <div class="text-xs font-semibold uppercase tracking-wider text-white/50">{{ __('Our other brands') }}</div>
                <div class="mt-3 flex flex-wrap gap-3">
                    @foreach ($sisterCompanies as $sisterCompany)
                        <a class="rounded-lg border border-white/15 px-4 py-2 text-sm font-medium text-white/80 transition hover:border-[var(--storefront-accent)] hover:text-white" href="https://{{ $sisterCompany->domain }}" target="_blank" rel="noopener">{{ $sisterCompany->name }}</a>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    @if ($footerGridBlocks->isNotEmpty())
        <div class="mp-shell grid gap-7 py-9 md:grid-cols-3">
            @foreach ($footerGridBlocks as $block)
                @includeIf('storefront.partials.footer-blocks.'.$block['type'], ['data' => $block['data'] ?? []])
            @endforeach
        </div>
    @endif

    @if ($footerBottomBar)
        @include('storefront.partials.footer-blocks.bottom_bar', ['data' => $footerBottomBar['data'] ?? []])
    @endif
</footer>
