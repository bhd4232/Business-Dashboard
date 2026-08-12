@php
    $activeSectionItem = collect($sectionItems)->firstWhere('key', $activeSection);
@endphp

<div class="zz-meta-custom-header">
    @if ($sectionItems !== [])
        <div class="zz-meta-mobile-nav" aria-label="Meta CAPI mobile navigation">
            <x-filament::section compact class="zz-meta-mobile-nav-panel">
                <div class="flex min-h-11 items-center gap-3">
                    <x-filament::dropdown
                        placement="bottom-start"
                        width="sm"
                        shift
                    >
                        <x-slot name="trigger">
                            <x-filament::icon-button
                                color="gray"
                                :icon="\Filament\Support\Icons\Heroicon::OutlinedBars3"
                                icon-size="lg"
                                size="xl"
                                label="Open Meta CAPI navigation"
                                class="zz-meta-mobile-nav-toggle"
                            />
                        </x-slot>

                        <x-filament::dropdown.list>
                            @foreach ($sectionItems as $item)
                                <x-filament::dropdown.list.item
                                    :icon="$item['icon']"
                                    :color="$activeSection === $item['key'] ? 'primary' : 'gray'"
                                    :aria-current="$activeSection === $item['key'] ? 'page' : null"
                                    :data-active="$activeSection === $item['key'] ? 'true' : 'false'"
                                    :data-section="$item['key']"
                                    wire:click="selectSection('{{ $item['key'] }}')"
                                    wire:target="selectSection"
                                    x-on:click="close()"
                                    wire:key="meta-capi-mobile-section-{{ $item['key'] }}"
                                    class="{{ $activeSection === $item['key'] ? 'fi-selected' : '' }}"
                                >
                                    {{ $item['label'] }}
                                </x-filament::dropdown.list.item>
                            @endforeach
                        </x-filament::dropdown.list>
                    </x-filament::dropdown>

                    <span class="text-sm font-semibold text-gray-950 dark:text-white">
                        {{ $activeSectionItem['label'] ?? 'Meta CAPI sections' }}
                    </span>
                </div>
            </x-filament::section>
        </div>
    @endif

    <x-filament-panels::header
        :actions="$headerActions"
        :actions-alignment="$headerActionsAlignment"
        :breadcrumbs="$breadcrumbs"
        :heading="$heading"
        :subheading="$subheading"
    />
</div>
