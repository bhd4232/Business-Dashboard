<x-filament-panels::page class="zz-meta-capi-page">
    @php
        $sectionItems = $this->sectionNavigation();
    @endphp

    <div class="zz-meta-shell">
        @if ($sectionItems !== [])
            <aside class="zz-meta-rail" aria-label="Meta CAPI page sections">
                <x-filament::section compact class="zz-meta-nav-panel">
                    <nav class="zz-meta-nav-list">
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
                                wire:key="meta-capi-section-{{ $item['key'] }}"
                                class="zz-meta-nav-link {{ $activeSection === $item['key'] ? 'zz-meta-nav-link-active' : '' }}"
                            >
                                <span class="zz-meta-nav-label">{{ $item['label'] }}</span>
                            </x-filament::button>
                        @endforeach
                    </nav>
                </x-filament::section>
            </aside>

        @endif

        <div class="min-w-0 space-y-6">
            @if (! $this->hasSelectedCompany() && $this->canManageMetaSettings())
                <x-filament::section
                    icon="heroicon-o-building-office-2"
                    icon-color="warning"
                    heading="Select a company"
                    description="Meta configuration is company-specific. Choose a company from the top bar before editing these settings."
                />
            @elseif ($this->canManageMetaSettings())
                <form id="meta-capi-settings-form" wire:submit="save">
                    {{ $this->form }}
                </form>
            @endif

            @if ($this->canViewMetaEvents() && $activeSection === 'event_log')
                <div id="meta-event-log" class="zz-meta-anchor space-y-4">
                    <x-filament::section
                        icon="heroicon-o-queue-list"
                        heading="Event Log & Retries"
                        description="Inspect privacy-minimized per-Pixel delivery attempts and queue a retry for failed or pending events."
                    />

                    {{ $this->table }}
                </div>
            @endif
        </div>
    </div>

    <style>
        .zz-meta-custom-header {
            position: sticky;
            top: 4rem;
            z-index: 20;
        }

        .zz-meta-custom-header .fi-header-subheading {
            font-size: var(--text-sm);
            line-height: var(--text-sm--line-height);
        }

        .zz-meta-shell {
            display: grid;
            grid-template-columns: 4.5rem minmax(0, 1fr);
            gap: 1rem;
            align-items: start;
        }

        .zz-meta-rail {
            position: sticky;
            top: 9rem;
            z-index: 10;
            width: 4.5rem;
            min-height: 1px;
        }

        .zz-meta-mobile-nav {
            display: none;
        }

        .zz-meta-nav-panel {
            position: absolute;
            inset-block-start: 0;
            inset-inline-start: 0;
            width: 18rem;
            clip-path: inset(0 13.5rem 0 0 round .75rem);
            transition: clip-path 180ms ease, filter 180ms ease;
        }

        .zz-meta-rail:hover .zz-meta-nav-panel,
        .zz-meta-rail:focus-within .zz-meta-nav-panel {
            clip-path: inset(0 0 0 0 round .75rem);
            filter: drop-shadow(8px 12px 18px rgb(15 23 42 / .12));
        }

        .dark .zz-meta-rail:hover .zz-meta-nav-panel,
        .dark .zz-meta-rail:focus-within .zz-meta-nav-panel {
            filter: drop-shadow(8px 12px 18px rgb(0 0 0 / .3));
        }

        .zz-meta-nav-list {
            display: grid;
            gap: .5rem;
        }

        .zz-meta-nav-link.fi-btn {
            justify-content: flex-start;
            inline-size: 2.5rem;
            min-height: 2.75rem;
            overflow: hidden;
            padding-inline: .625rem;
            white-space: nowrap;
            transition: inline-size 180ms ease;
        }

        .zz-meta-rail:hover .zz-meta-nav-link.fi-btn,
        .zz-meta-rail:focus-within .zz-meta-nav-link.fi-btn {
            inline-size: 100%;
        }

        .zz-meta-nav-label {
            display: none;
            margin-inline-start: .25rem;
            pointer-events: none;
        }

        .zz-meta-rail:hover .zz-meta-nav-label,
        .zz-meta-rail:focus-within .zz-meta-nav-label {
            display: inline-flex;
        }

        .zz-meta-anchor {
            scroll-margin-top: 9rem;
        }

        @media (max-width: 1023px) {
            .zz-meta-shell {
                grid-template-columns: minmax(0, 1fr);
            }

            .zz-meta-capi-page .fi-page-header-main-ctn {
                gap-y: 0;
            }

            .zz-meta-custom-header > .fi-header {
                margin-top: 0;
                margin-bottom: 0;
            }

            .zz-meta-rail {
                display: none;
            }

            .zz-meta-mobile-nav {
                display: block;
                position: relative;
                background-color: var(--gray-50);
                padding-top: 10px;
            }

            .dark .zz-meta-mobile-nav {
                background-color: var(--gray-950);
            }

            .zz-meta-mobile-nav-panel {
                border-block-start: 1px solid var(--gray-200);
            }

            .dark .zz-meta-mobile-nav-panel {
                border-block-start-color: var(--gray-700);
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .zz-meta-nav-panel,
            .zz-meta-nav-link.fi-btn {
                transition: none;
            }
        }
    </style>
</x-filament-panels::page>
