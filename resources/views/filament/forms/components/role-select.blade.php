@php
    $fieldWrapperView = $getFieldWrapperView();
    $id = $getId();
    $isDisabled = $isDisabled();
    $isRequired = $isRequired();
    $isPrefixInline = $isPrefixInline();
    $isSuffixInline = $isSuffixInline();
    $prefixActions = $getPrefixActions();
    $prefixIcon = $getPrefixIcon();
    $prefixIconColor = $getPrefixIconColor();
    $prefixLabel = $getPrefixLabel();
    $suffixActions = $getSuffixActions();
    $suffixIcon = $getSuffixIcon();
    $suffixIconColor = $getSuffixIconColor();
    $suffixLabel = $getSuffixLabel();
    $statePath = $getStatePath();
    $state = $getRawState() ?: 'sales_staff';
    $options = $getOptions();
@endphp

@once
    <style>
        .zz-role-select {
            position: relative;
            width: 100%;
        }

        .zz-role-select-button {
            align-items: center;
            background: transparent;
            border: 0;
            color: rgb(17 24 39);
            cursor: pointer;
            display: flex;
            font: inherit;
            gap: 0.5rem;
            min-height: 2.625rem;
            padding: 0 0.75rem;
            text-align: start;
            width: 100%;
        }

        .dark .zz-role-select-button {
            color: rgb(255 255 255);
        }

        .zz-role-select-button:disabled {
            cursor: not-allowed;
            opacity: 0.7;
        }

        .zz-role-select-label {
            flex: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .zz-role-select-icon {
            color: rgb(107 114 128);
            height: 1rem;
            width: 1rem;
        }

        .zz-role-select-panel {
            background: white;
            border: 1px solid rgb(229 231 235);
            border-radius: 0.5rem;
            box-shadow: 0 12px 30px rgb(0 0 0 / 0.16);
            left: 0;
            margin-top: 0.25rem;
            max-height: 16rem;
            overflow: auto;
            padding: 0.375rem;
            position: absolute;
            right: 0;
            z-index: 50;
        }

        .dark .zz-role-select-panel {
            background: rgb(24 24 27);
            border-color: rgb(63 63 70);
            box-shadow: 0 12px 30px rgb(0 0 0 / 0.45);
        }

        .zz-role-select-option {
            align-items: center;
            background: transparent;
            border: 0;
            border-radius: 0.375rem;
            color: rgb(17 24 39);
            cursor: pointer;
            display: flex;
            font: inherit;
            min-height: 2.25rem;
            padding: 0.375rem 0.625rem;
            text-align: start;
            width: 100%;
        }

        .zz-role-select-option:hover,
        .zz-role-select-option:focus {
            background: rgb(245 158 11 / 0.12);
            outline: none;
        }

        .zz-role-select-option[aria-selected='true'] {
            background: rgb(245 158 11 / 0.18);
            color: rgb(180 83 9);
            font-weight: 700;
        }

        .dark .zz-role-select-option {
            color: rgb(244 244 245);
        }

        .dark .zz-role-select-option:hover,
        .dark .zz-role-select-option:focus {
            background: rgb(245 158 11 / 0.18);
        }

        .dark .zz-role-select-option[aria-selected='true'] {
            color: rgb(252 211 77);
        }
    </style>
@endonce

<x-dynamic-component :component="$fieldWrapperView" :field="$field">
    <div
        class="fi-input-wrp fi-fo-select"
        x-data="{
            open: false,
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
            options: @js($options),
            fallback: @js($state),
            label() {
                return this.options[this.state || this.fallback] || 'Select an option'
            },
            choose(value) {
                this.state = value
                this.open = false
            },
        }"
        x-on:keydown.escape.window="open = false"
        x-on:click.outside="open = false"
    >
        <div class="fi-input-wrp-content-ctn">
            <div class="zz-role-select">
                <input
                    id="{{ $id }}"
                    type="hidden"
                    @if ($isRequired)
                        required
                    @endif
                    x-model="state"
                />

                <button
                    type="button"
                    class="zz-role-select-button"
                    x-on:click="open = ! open"
                    @if ($isDisabled)
                        disabled
                    @endif
                    aria-haspopup="listbox"
                    x-bind:aria-expanded="open.toString()"
                >
                    <span class="zz-role-select-label" x-text="label()"></span>
                    <svg class="zz-role-select-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M5.22 7.22a.75.75 0 0 1 1.06 0L10 10.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 8.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                    </svg>
                </button>

                <div
                    x-cloak
                    x-show="open"
                    x-transition.opacity.duration.100ms
                    class="zz-role-select-panel"
                    role="listbox"
                >
                    @foreach ($options as $value => $label)
                        <button
                            type="button"
                            class="zz-role-select-option"
                            role="option"
                            x-on:click="choose(@js((string) $value))"
                            x-bind:aria-selected="(state || fallback) === @js((string) $value)"
                        >
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        @if (count($suffixActions))
            <div class="fi-input-wrp-suffix fi-input-wrp-suffix-has-content fi-inline">
                @foreach ($suffixActions as $suffixAction)
                    {{ $suffixAction }}
                @endforeach
            </div>
        @endif
    </div>
</x-dynamic-component>
