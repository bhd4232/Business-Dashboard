<x-filament-panels::page>
    <style>
        .zz-setup {
            display: grid;
            gap: 18px;
        }

        .zz-setup-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.1fr) minmax(320px, .9fr);
            gap: 18px;
        }

        .zz-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            overflow: hidden;
        }

        .dark .zz-card {
            background: #17181c;
            border-color: #2b2d33;
        }

        .zz-card-header {
            padding: 16px;
            background: #f8fafc;
            border-bottom: 1px solid #e5e7eb;
        }

        .dark .zz-card-header {
            background: #101827;
            border-bottom-color: #283244;
        }

        .zz-title {
            margin: 0;
            color: #111827;
            font-size: 18px;
            font-weight: 850;
        }

        .dark .zz-title {
            color: #f7f8fb;
        }

        .zz-desc,
        .zz-muted {
            color: #64748b;
            font-size: 13px;
        }

        .dark .zz-desc,
        .dark .zz-muted {
            color: #a8adb8;
        }

        .zz-form {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            padding: 16px;
        }

        .zz-field {
            display: grid;
            gap: 7px;
        }

        .zz-field-full {
            grid-column: 1 / -1;
        }

        .zz-field label,
        .zz-check {
            color: #111827;
            font-size: 13px;
            font-weight: 800;
        }

        .dark .zz-field label,
        .dark .zz-check {
            color: #f7f8fb;
        }

        .zz-input,
        .zz-select {
            width: 100%;
            height: 40px;
            padding: 0 12px;
            color: #111827;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            outline: none;
        }

        .dark .zz-input,
        .dark .zz-select {
            color: #f7f8fb;
            background: #222329;
            border-color: #3a3d45;
        }

        .zz-check {
            display: inline-flex;
            align-items: center;
            gap: 9px;
        }

        .zz-check input {
            width: 18px;
            height: 18px;
            accent-color: #f59e0b;
        }

        .zz-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding: 0 16px 16px;
        }

        .zz-button {
            min-height: 40px;
            padding: 0 14px;
            color: #111827;
            background: #f59e0b;
            border: 1px solid #f59e0b;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 800;
            cursor: pointer;
        }

        .zz-list {
            display: grid;
            gap: 10px;
            padding: 16px;
        }

        .zz-list-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            min-height: 44px;
            padding: 10px 12px;
            color: #111827;
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 800;
        }

        .dark .zz-list-item {
            color: #f7f8fb;
            background: #202127;
            border-color: #30333b;
        }

        .zz-pill {
            display: inline-flex;
            align-items: center;
            min-height: 26px;
            padding: 0 9px;
            color: #92400e;
            background: #fef3c7;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 850;
        }

        .zz-pill.is-done {
            color: #047857;
            background: #d1fae5;
        }

        @media (max-width: 980px) {
            .zz-setup-grid,
            .zz-form {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="zz-setup">
        <div class="zz-setup-grid">
            <section class="zz-card">
                <div class="zz-card-header">
                    <h2 class="zz-title">Onboarding Wizard</h2>
                    <p class="zz-desc">Finish the client-ready setup after install.</p>
                </div>

                <form wire:submit.prevent="saveSetup">
                    <div class="zz-form">
                        <div class="zz-field">
                            <label for="setup-company-name">Company Name</label>
                            <input id="setup-company-name" class="zz-input" type="text" wire:model="companyName">
                            @error('companyName') <span class="zz-muted">{{ $message }}</span> @enderror
                        </div>

                        <div class="zz-field">
                            <label for="setup-company-email">Company Email</label>
                            <input id="setup-company-email" class="zz-input" type="email" wire:model="companyEmail">
                            @error('companyEmail') <span class="zz-muted">{{ $message }}</span> @enderror
                        </div>

                        <div class="zz-field">
                            <label for="setup-currency">Currency</label>
                            <input id="setup-currency" class="zz-input" type="text" wire:model="currency">
                            @error('currency') <span class="zz-muted">{{ $message }}</span> @enderror
                        </div>

                        <div class="zz-field">
                            <label for="setup-timezone">Timezone</label>
                            <select id="setup-timezone" class="zz-select" wire:model="timezone">
                                @foreach ($this->timezoneOptions() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('timezone') <span class="zz-muted">{{ $message }}</span> @enderror
                        </div>

                        <div class="zz-field">
                            <label for="setup-date-format">Date Format</label>
                            <select id="setup-date-format" class="zz-select" wire:model="dateFormat">
                                @foreach ($this->dateFormatOptions() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('dateFormat') <span class="zz-muted">{{ $message }}</span> @enderror
                        </div>

                        <label class="zz-check">
                            <input type="checkbox" wire:model="onboardingCompleted">
                            Mark onboarding complete
                        </label>

                        <label class="zz-check">
                            <input type="checkbox" wire:model="demoMode">
                            Enable demo mode
                        </label>

                        <div class="zz-field zz-field-full">
                            <label for="setup-demo-notice">Demo Notice</label>
                            <input id="setup-demo-notice" class="zz-input" type="text" wire:model="demoNotice">
                            @error('demoNotice') <span class="zz-muted">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="zz-actions">
                        <button type="submit" class="zz-button">Save Setup</button>
                    </div>
                </form>
            </section>

            <section class="zz-card">
                <div class="zz-card-header">
                    <h2 class="zz-title">Setup Checklist</h2>
                    <p class="zz-desc">A quick sellable-product readiness snapshot.</p>
                </div>
                <div class="zz-list">
                    @foreach ($this->setupChecklist() as $item)
                        <div class="zz-list-item">
                            <span>{{ $item['label'] }}</span>
                            <span class="zz-pill {{ $item['done'] ? 'is-done' : '' }}">{{ $item['done'] ? 'Done' : 'Open' }}</span>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>

        <section class="zz-card">
            <div class="zz-card-header">
                <h2 class="zz-title">License Activation</h2>
                <p class="zz-desc">
                    Current status: {{ $this->licenseDetails()['is_active'] ? 'Active' : 'Not activated' }}
                    ({{ $this->licenseDetails()['masked_key'] }})
                </p>
            </div>

            <form wire:submit.prevent="activateLicense">
                <div class="zz-form">
                    <div class="zz-field">
                        <label for="setup-license-key">License Key</label>
                        <input id="setup-license-key" class="zz-input" type="text" wire:model="licenseKey" placeholder="ZZERP-XXXX-XXXX-XXXX">
                        @error('licenseKey') <span class="zz-muted">{{ $message }}</span> @enderror
                    </div>

                    <div class="zz-field">
                        <label for="setup-licensed-to">Licensed To</label>
                        <input id="setup-licensed-to" class="zz-input" type="text" wire:model="licensedTo">
                        @error('licensedTo') <span class="zz-muted">{{ $message }}</span> @enderror
                    </div>

                    <div class="zz-field">
                        <label for="setup-support-email">Support Email</label>
                        <input id="setup-support-email" class="zz-input" type="email" wire:model="supportEmail">
                        @error('supportEmail') <span class="zz-muted">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="zz-actions">
                    <button type="submit" class="zz-button">Activate License</button>
                </div>
            </form>
        </section>
    </div>
</x-filament-panels::page>
