<x-filament-panels::page class="zz-storefront-settings-page">
    @php
        $sectionItems = $this->sectionNavigation();
    @endphp

    <div class="zz-storefront-settings-shell">
        <aside class="zz-storefront-settings-rail" aria-label="Storefront settings sections">
            <x-filament::section compact class="zz-storefront-settings-nav-panel">
                <nav class="zz-storefront-settings-nav-list">
                    @foreach ($sectionItems as $item)
                        <x-filament::button
                            type="button"
                            :icon="$item['icon']"
                            :color="$activeSection === $item['key'] ? 'primary' : 'gray'"
                            :outlined="$activeSection !== $item['key']"
                            :aria-current="$activeSection === $item['key'] ? 'page' : null"
                            :aria-label="$item['label']"
                            :data-active="$activeSection === $item['key'] ? 'true' : 'false'"
                            :data-section="$item['key']"
                            wire:click="selectSection('{{ $item['key'] }}')"
                            wire:loading.attr="disabled"
                            wire:target="selectSection"
                            wire:key="storefront-settings-section-{{ $item['key'] }}"
                            class="zz-storefront-settings-nav-link {{ $activeSection === $item['key'] ? 'zz-storefront-settings-nav-link-active' : '' }}"
                        >
                            <span class="zz-storefront-settings-nav-label">{{ $item['label'] }}</span>
                        </x-filament::button>
                    @endforeach
                </nav>
            </x-filament::section>
        </aside>

        <div class="min-w-0">
            {{ $this->content }}
        </div>
    </div>

    <style>
        .zz-storefront-settings-custom-header {
            position: sticky;
            top: 4rem;
            z-index: 20;
        }

        .zz-storefront-settings-shell {
            display: grid;
            grid-template-columns: 4.5rem minmax(0, 1fr);
            gap: 1rem;
            align-items: start;
        }

        .zz-storefront-settings-rail {
            position: sticky;
            top: 9rem;
            z-index: 10;
            width: 4.5rem;
            min-height: 1px;
        }

        .zz-storefront-settings-mobile-nav {
            display: none;
        }

        .zz-storefront-settings-nav-panel {
            position: absolute;
            inset-block-start: 0;
            inset-inline-start: 0;
            width: 18rem;
            clip-path: inset(0 13.5rem 0 0 round .75rem);
            transition: clip-path 180ms ease, filter 180ms ease;
        }

        .zz-storefront-settings-rail:hover .zz-storefront-settings-nav-panel,
        .zz-storefront-settings-rail:focus-within .zz-storefront-settings-nav-panel {
            clip-path: inset(0 0 0 0 round .75rem);
            filter: drop-shadow(8px 12px 18px rgb(15 23 42 / .12));
        }

        .dark .zz-storefront-settings-rail:hover .zz-storefront-settings-nav-panel,
        .dark .zz-storefront-settings-rail:focus-within .zz-storefront-settings-nav-panel {
            filter: drop-shadow(8px 12px 18px rgb(0 0 0 / .3));
        }

        .zz-storefront-settings-nav-list {
            display: grid;
            gap: .5rem;
        }

        .zz-storefront-settings-nav-link.fi-btn {
            justify-content: flex-start;
            inline-size: 2.5rem;
            min-height: 2.75rem;
            overflow: hidden;
            padding-inline: .625rem;
            white-space: nowrap;
            transition: inline-size 180ms ease;
        }

        .zz-storefront-settings-rail:hover .zz-storefront-settings-nav-link.fi-btn,
        .zz-storefront-settings-rail:focus-within .zz-storefront-settings-nav-link.fi-btn {
            inline-size: 100%;
        }

        .zz-storefront-settings-nav-label {
            display: none;
            margin-inline-start: .25rem;
            pointer-events: none;
        }

        .zz-storefront-settings-rail:hover .zz-storefront-settings-nav-label,
        .zz-storefront-settings-rail:focus-within .zz-storefront-settings-nav-label {
            display: inline-flex;
        }

        .zz-storefront-settings-anchor {
            scroll-margin-top: 9rem;
        }

        @media (max-width: 1023px) {
            .zz-storefront-settings-shell {
                grid-template-columns: minmax(0, 1fr);
            }

            .zz-storefront-settings-page .fi-page-header-main-ctn {
                gap-y: 0;
            }

            .zz-storefront-settings-custom-header > .fi-header {
                margin-top: 0;
                margin-bottom: 0;
            }

            .zz-storefront-settings-rail {
                display: none;
            }

            .zz-storefront-settings-mobile-nav {
                display: block;
                position: relative;
                background-color: var(--gray-50);
                padding-top: 10px;
            }

            .dark .zz-storefront-settings-mobile-nav {
                background-color: var(--gray-950);
            }

            .zz-storefront-settings-mobile-nav-panel {
                border-block-start: 1px solid var(--gray-200);
            }

            .dark .zz-storefront-settings-mobile-nav-panel {
                border-block-start-color: var(--gray-700);
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .zz-storefront-settings-nav-panel,
            .zz-storefront-settings-nav-link.fi-btn {
                transition: none;
            }
        }
    </style>
</x-filament-panels::page>
