@php
    $fieldWrapperView = $getFieldWrapperView();
    $extraAttributeBag = $getExtraAttributeBag();
    $id = $getId();
    $isDisabled = $isDisabled();
    $isRequired = $isRequired();
    $statePath = $getStatePath();
@endphp

<x-dynamic-component
    :component="$fieldWrapperView"
    :field="$field"
>
    <x-filament::input.wrapper
        :disabled="$isDisabled"
        :valid="! $errors->has($statePath)"
        :attributes="
            \Filament\Support\prepare_inherited_attributes($extraAttributeBag)
                ->class(['fi-fo-phone-number-input'])
        "
    >
        <div
            x-data="{
                state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
                countryCode: '+880',
                countryIso: 'BD',
                number: '',
                open: false,
                search: '',
                isSyncing: false,
                options: @js($getCountryOptions()),
                init() {
                    this.syncFromState(this.state)

                    this.$watch('state', (value) => {
                        if (this.isSyncing) {
                            return
                        }

                        this.syncFromState(value)
                    })
                },
                selected() {
                    return this.options.find((option) => option.code === this.countryCode && option.iso === this.countryIso)
                        || this.options.find((option) => option.code === this.countryCode)
                        || this.options[0]
                },
                flagUrl(iso) {
                    return `https://flagcdn.com/24x18/${iso.toLowerCase()}.png`
                },
                filteredOptions() {
                    const query = this.search.trim().toLowerCase()

                    if (! query) {
                        return this.options
                    }

                    return this.options.filter((option) => [
                        option.code,
                        option.iso,
                        option.country,
                    ].join(' ').toLowerCase().includes(query))
                },
                cleanSearch(value) {
                    this.search = value.replace(/[^A-Za-z0-9+\s.'-]/g, '')
                },
                choose(option) {
                    this.countryCode = option.code
                    this.countryIso = option.iso
                    this.open = false
                    this.search = ''
                    this.syncToState()
                    this.$refs.phoneInput?.focus()
                },
                syncFromState(value) {
                    const raw = (value || '').toString().trim()

                    if (! raw) {
                        this.countryCode = this.countryCode || '+880'
                        this.countryIso = this.countryIso || 'BD'
                        this.number = ''

                        return
                    }

                    const option = [...this.options]
                        .sort((first, second) => second.code.length - first.code.length)
                        .find((item) => raw.startsWith(item.code))

                    if (option) {
                        this.countryCode = option.code
                        this.countryIso = option.iso
                        this.number = raw.slice(option.code.length)

                        return
                    }

                    this.number = raw
                },
                syncToState() {
                    const raw = this.number.trim()

                    this.isSyncing = true

                    if (! raw) {
                        this.state = null
                    } else if (raw.startsWith('+')) {
                        this.state = raw.replace(/\s+/g, '')
                    } else {
                        const digits = raw.replace(/[^\d]/g, '')
                        this.state = digits ? `${this.countryCode}${digits.replace(/^0+/, '')}` : null
                    }

                    queueMicrotask(() => {
                        this.isSyncing = false
                    })
                },
            }"
            style="position: relative; display: flex; align-items: stretch; width: 100%; height: 2.5rem; min-height: 2.5rem; overflow: visible;"
        >
            <div style="position: relative; flex: 0 0 auto;">
                <button
                    type="button"
                    x-on:click="open = ! open"
                    @disabled($isDisabled)
                    style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; min-width: 7.25rem; height: 100%; padding: 0 0.75rem; border: 0; border-inline-end: 1px solid rgba(156, 163, 175, 0.35); background: transparent; color: inherit; cursor: pointer; font-size: 0.875rem; font-weight: 400; line-height: 1.25rem; outline: none; white-space: nowrap;"
                >
                    <span style="display: inline-flex; width: 1.5rem; height: 1.125rem; align-items: center; justify-content: center; overflow: hidden; border-radius: 0.125rem; background: rgba(156, 163, 175, 0.12);">
                        <img
                            x-bind:src="flagUrl(selected().iso)"
                            x-bind:alt="`${selected().country} flag`"
                            loading="lazy"
                            style="display: block; width: 1.5rem; height: 1.125rem; object-fit: cover;"
                            x-on:error="$el.style.display = 'none'; $el.nextElementSibling.style.display = 'inline-flex'"
                        />
                        <span style="display: none; width: 100%; height: 100%; align-items: center; justify-content: center; font-size: 0.625rem; font-weight: 500; line-height: 1; color: rgb(209, 213, 219);" x-text="selected().iso"></span>
                    </span>
                    <span x-text="selected().iso"></span>
                    <span x-text="selected().code"></span>
                    <svg style="width: 1rem; height: 1rem; flex: none; margin-inline-start: auto; color: rgb(156, 163, 175);" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
                    </svg>
                </button>

                <div
                    x-cloak
                    x-show="open"
                    x-transition.origin.top.left
                    x-on:click.outside="open = false"
                    style="position: absolute; inset-inline-start: 0; top: 100%; z-index: 50; margin-top: 0.5rem; width: 20rem; overflow: hidden; border-radius: 0.5rem; background: rgb(24, 24, 27); box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.2), 0 8px 10px -6px rgb(0 0 0 / 0.2); border: 1px solid rgba(255, 255, 255, 0.1);"
                >
                    <div style="border-bottom: 1px solid rgba(255, 255, 255, 0.1); padding: 0.5rem;">
                        <input
                            type="search"
                            x-model="search"
                            x-on:input="cleanSearch($event.target.value)"
                            placeholder="Search country or code"
                            lang="en"
                            inputmode="search"
                            autocomplete="off"
                            autocapitalize="none"
                            spellcheck="false"
                            style="width: 100%; border-radius: 0.375rem; border: 0; background: rgba(255, 255, 255, 0.05); padding: 0.5rem 0.75rem; color: rgb(255, 255, 255); outline: none; font-size: 0.875rem;"
                        />
                    </div>

                    <div style="max-height: 16rem; overflow-y: auto; padding: 0.25rem 0;">
                        <template x-for="option in filteredOptions()" x-bind:key="option.iso">
                            <button
                                type="button"
                                x-on:click="choose(option)"
                                style="display: flex; width: 100%; align-items: center; gap: 0.75rem; border: 0; background: transparent; padding: 0.5rem 0.75rem; color: rgb(229, 231, 235); cursor: pointer; text-align: start; font-size: 0.875rem; font-weight: 400; line-height: 1.25rem;"
                            >
                                <span style="display: inline-flex; width: 1.5rem; height: 1.125rem; flex: none; align-items: center; justify-content: center; overflow: hidden; border-radius: 0.125rem; background: rgba(156, 163, 175, 0.12);">
                                    <img
                                        x-bind:src="flagUrl(option.iso)"
                                        x-bind:alt="`${option.country} flag`"
                                        loading="lazy"
                                        style="display: block; width: 1.5rem; height: 1.125rem; object-fit: cover;"
                                        x-on:error="$el.style.display = 'none'; $el.nextElementSibling.style.display = 'inline-flex'"
                                    />
                                    <span style="display: none; width: 100%; height: 100%; align-items: center; justify-content: center; font-size: 0.625rem; font-weight: 500; line-height: 1; color: rgb(209, 213, 219);" x-text="option.iso"></span>
                                </span>
                                <span style="width: 2rem; flex: none; color: rgb(229, 231, 235); font-weight: 400;" x-text="option.iso"></span>
                                <span style="min-width: 0; flex: 1 1 auto; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 400;" x-text="option.country"></span>
                                <span style="font-weight: 400; color: rgb(255, 255, 255);" x-text="option.code"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <input
                id="{{ $id }}"
                x-ref="phoneInput"
                x-model="number"
                x-on:input="syncToState()"
                type="tel"
                inputmode="tel"
                placeholder="01712 345678"
                @required($isRequired)
                @disabled($isDisabled)
                maxlength="255"
                style="min-width: 0; flex: 1 1 auto; width: 100%; height: 100%; border: 0; background: transparent; padding: 0 1rem; color: inherit; outline: none; font-size: 0.875rem; line-height: 1.25rem;"
            />
        </div>
    </x-filament::input.wrapper>
</x-dynamic-component>
