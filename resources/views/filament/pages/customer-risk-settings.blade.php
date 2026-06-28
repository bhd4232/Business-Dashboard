<x-filament-panels::page>
    <form wire:submit.prevent="save">
        <x-filament::section
            icon="heroicon-o-adjustments-horizontal"
            icon-color="warning"
            heading="Rule thresholds and deductions"
            description="Adjust the explainable rule engine without changing code. Higher deductions lower the customer risk score faster."
        >
            <div class="zz-risk-settings-grid">
                @foreach ($this->labels() as $key => $label)
                    @php($errorKey = "settings.{$key}")

                    <div class="zz-risk-field">
                        <label for="risk-setting-{{ $key }}" class="zz-risk-label">
                            {{ $label }}
                        </label>

                        <x-filament::input.wrapper :valid="! $errors->has($errorKey)">
                            <x-filament::input
                                id="risk-setting-{{ $key }}"
                                type="number"
                                min="0"
                                wire:model.defer="settings.{{ $key }}"
                            />
                        </x-filament::input.wrapper>

                        @error($errorKey)
                            <p class="zz-risk-error">{{ $message }}</p>
                        @enderror
                    </div>
                @endforeach
            </div>

            <x-slot name="footer">
                <div class="zz-risk-actions">
                    <x-filament::button type="submit" icon="heroicon-m-check">
                        Save risk settings
                    </x-filament::button>
                </div>
            </x-slot>
        </x-filament::section>
    </form>

    <style>
        .zz-risk-settings-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .zz-risk-field {
            display: grid;
            gap: .4rem;
        }

        .zz-risk-label {
            color: var(--gray-700);
            font-size: .875rem;
            font-weight: 650;
            line-height: 1.35;
        }

        .dark .zz-risk-label {
            color: var(--gray-200);
        }

        .zz-risk-error {
            color: var(--danger-600);
            font-size: .8125rem;
            line-height: 1.4;
        }

        .dark .zz-risk-error {
            color: var(--danger-400);
        }

        .zz-risk-actions {
            display: flex;
            justify-content: flex-start;
        }

        @media (max-width: 768px) {
            .zz-risk-settings-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</x-filament-panels::page>
