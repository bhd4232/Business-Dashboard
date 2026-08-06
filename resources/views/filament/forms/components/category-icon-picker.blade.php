@php
    $fieldWrapperView = $getFieldWrapperView();
    $id = $getId();
    $isDisabled = $isDisabled();
    $statePath = $getStatePath();
    $modalId = $id . '-browser';
    $selectedIcon = \App\Support\StorefrontCategoryIcons::normalize($getState());
    $selectedLabel = \App\Support\StorefrontCategoryIcons::label($selectedIcon);
@endphp

<x-dynamic-component
    :component="$fieldWrapperView"
    :field="$field"
>
    <div
        x-data="{
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
        }"
        class="space-y-3"
    >
        <div class="flex flex-wrap items-center gap-2">
            <x-filament::modal
                :id="$modalId"
                width="7xl"
                sticky-header
                heading="Choose a category icon"
                description="Search and select from every solid and outline icon included with Filament."
            >
                <x-slot name="trigger">
                    <x-filament::button
                        type="button"
                        color="gray"
                        outlined
                        icon="heroicon-o-squares-2x2"
                        :disabled="$isDisabled"
                    >
                        <span x-text="state ? 'Change icon' : 'Choose icon'"></span>
                    </x-filament::button>
                </x-slot>

                <div
                    x-data="{
                        state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
                        choose(value) {
                            this.state = value
                            this.$dispatch('close-modal', { id: @js($modalId) })
                        },
                        clear() {
                            this.state = null
                            this.$dispatch('close-modal', { id: @js($modalId) })
                        },
                    }"
                    class="space-y-4"
                    data-zz-category-icon-browser
                >
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <x-filament::input.wrapper
                            prefix-icon="heroicon-o-magnifying-glass"
                            class="flex-1"
                        >
                            <x-filament::input
                                type="search"
                                placeholder="Search icons by name or style..."
                                autocomplete="off"
                                spellcheck="false"
                                data-zz-category-icon-search-input
                            />
                        </x-filament::input.wrapper>

                        <x-filament::button
                            type="button"
                            color="gray"
                            outlined
                            icon="heroicon-o-x-mark"
                            x-on:click="clear()"
                            data-zz-category-icon-reset
                        >
                            Use category initial
                        </x-filament::button>
                    </div>

                    <div
                        class="grid max-h-[60vh] grid-cols-2 gap-2 overflow-y-auto p-1 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8"
                        role="listbox"
                        aria-label="Filament category icons"
                    >
                        @foreach ($getIcons() as $icon)
                            <x-filament::button
                                type="button"
                                color="gray"
                                outlined
                                size="sm"
                                :icon="$icon['value']"
                                :tooltip="$icon['label']"
                                x-on:click="choose(@js($icon['value']))"
                                x-bind:aria-selected="state === @js($icon['value'])"
                                x-bind:class="state === @js($icon['value']) ? 'ring-2 ring-primary-500' : ''"
                                :data-zz-category-icon-search="$icon['search']"
                                data-zz-category-icon-option
                                class="min-h-20 flex-col justify-center gap-2 text-center"
                                role="option"
                            >
                                <span class="line-clamp-2 text-xs">{{ $icon['label'] }}</span>
                            </x-filament::button>
                        @endforeach
                    </div>

                    <div data-zz-category-icon-empty hidden>
                        <x-filament::empty-state
                            heading="No matching icons"
                            description="Try a broader icon name, such as home, cart, phone, or box."
                            icon="heroicon-o-magnifying-glass"
                        />
                    </div>
                </div>
            </x-filament::modal>

            @if ($selectedIcon)
                <x-filament::badge
                    color="primary"
                    :icon="$selectedIcon"
                >
                    {{ $selectedLabel }}
                </x-filament::badge>
            @else
                <x-filament::badge color="gray" icon="heroicon-o-tag">
                    Category initial
                </x-filament::badge>
            @endif
        </div>
    </div>
</x-dynamic-component>
