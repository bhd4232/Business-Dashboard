<x-filament-panels::page>
    <style>
        .zz-company-settings {
            --zz-card-bg: #ffffff;
            --zz-card-border: #e5e7eb;
            --zz-header-bg: #f8fafc;
            --zz-header-border: #e5e7eb;
            --zz-title: #111827;
            --zz-muted: #64748b;
            --zz-label: #1f2937;
            --zz-field-bg: #ffffff;
            --zz-field-border: #cbd5e1;
            --zz-preview-bg: #f8fafc;
            --zz-error: #dc2626;

            display: grid;
            gap: 18px;
        }

        .dark .zz-company-settings {
            --zz-card-bg: #17181c;
            --zz-card-border: #2b2d33;
            --zz-header-bg: #101827;
            --zz-header-border: #283244;
            --zz-title: #f7f8fb;
            --zz-muted: #a8adb8;
            --zz-label: #f7f8fb;
            --zz-field-bg: #222329;
            --zz-field-border: #3a3d45;
            --zz-preview-bg: #222329;
            --zz-error: #fca5a5;
        }

        .zz-settings-card {
            background: var(--zz-card-bg);
            border: 1px solid var(--zz-card-border);
            border-radius: 10px;
            overflow: hidden;
        }

        .zz-settings-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 16px;
            background: var(--zz-header-bg);
            border-bottom: 1px solid var(--zz-header-border);
        }

        .zz-settings-title {
            margin: 0;
            color: var(--zz-title);
            font-size: 18px;
            font-weight: 850;
        }

        .zz-settings-desc {
            margin: 5px 0 0;
            color: var(--zz-muted);
            font-size: 13px;
        }

        .zz-settings-grid {
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

        .zz-field label {
            color: var(--zz-label);
            font-size: 13px;
            font-weight: 800;
        }

        .zz-input,
        .zz-textarea,
        .zz-select {
            width: 100%;
            color: var(--zz-title);
            background: var(--zz-field-bg);
            border: 1px solid var(--zz-field-border);
            border-radius: 8px;
            outline: none;
        }

        .zz-input,
        .zz-select {
            height: 40px;
            padding: 0 12px;
        }

        .zz-textarea {
            min-height: 92px;
            padding: 12px;
            resize: vertical;
        }

        .zz-logo-row {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .zz-logo-column {
            display: grid;
            gap: 10px;
            min-width: 0;
        }

        .zz-logo-preview {
            display: grid;
            place-items: center;
            width: 88px;
            height: 88px;
            color: var(--zz-muted);
            background: var(--zz-preview-bg);
            border: 1px solid var(--zz-field-border);
            border-radius: 8px;
            overflow: hidden;
            font-size: 12px;
            text-align: center;
        }

        .zz-logo-preview-dark {
            color: #cbd5e1;
            background: #111827;
            border-color: #374151;
        }

        .zz-logo-preview img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 8px;
            background: #ffffff;
        }

        .zz-logo-preview-dark img {
            background: #111827;
        }

        .zz-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding: 0 16px 16px;
        }

        .zz-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
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

        .zz-button-secondary {
            color: var(--zz-title);
            background: var(--zz-field-bg);
            border-color: var(--zz-field-border);
        }

        .zz-error {
            color: var(--zz-error);
            font-size: 12px;
        }

        @media (max-width: 760px) {
            .zz-settings-header,
            .zz-logo-row {
                align-items: stretch;
                flex-direction: column;
            }

            .zz-settings-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <form class="zz-company-settings" wire:submit.prevent="save">
        <section class="zz-settings-card">
            <div class="zz-settings-header">
                <div>
                    <h2 class="zz-settings-title">Business Profile</h2>
                    <p class="zz-settings-desc">Used on invoices, PDF reports, and white-label client installs.</p>
                </div>
                <button type="submit" class="zz-button" wire:loading.attr="disabled" wire:target="logoUpload,darkLogoUpload,save">Save Settings</button>
            </div>

            <div class="zz-settings-grid">
                <div class="zz-field">
                    <label for="company-name">Company Name</label>
                    <input id="company-name" class="zz-input" type="text" wire:model="name">
                    @error('name') <span class="zz-error">{{ $message }}</span> @enderror
                </div>

                <div class="zz-field">
                    <label for="company-email">Email</label>
                    <input id="company-email" class="zz-input" type="email" wire:model="email">
                    @error('email') <span class="zz-error">{{ $message }}</span> @enderror
                </div>

                <div class="zz-field">
                    <label for="company-phone">Phone</label>
                    <input id="company-phone" class="zz-input" type="text" wire:model="phone">
                    @error('phone') <span class="zz-error">{{ $message }}</span> @enderror
                </div>

                <div class="zz-field">
                    <label for="company-currency">Currency</label>
                    <input id="company-currency" class="zz-input" type="text" wire:model="currency">
                    @error('currency') <span class="zz-error">{{ $message }}</span> @enderror
                </div>

                <div class="zz-field">
                    <label for="company-timezone">Timezone</label>
                    <select id="company-timezone" class="zz-select" wire:model="timezone">
                        @foreach ($this->timezoneOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('timezone') <span class="zz-error">{{ $message }}</span> @enderror
                </div>

                <div class="zz-field">
                    <label for="company-date-format">Date Format</label>
                    <select id="company-date-format" class="zz-select" wire:model="dateFormat">
                        @foreach ($this->dateFormatOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('dateFormat') <span class="zz-error">{{ $message }}</span> @enderror
                </div>

                <div class="zz-field zz-field-full">
                    <label for="company-address">Address</label>
                    <textarea id="company-address" class="zz-textarea" wire:model="address"></textarea>
                    @error('address') <span class="zz-error">{{ $message }}</span> @enderror
                </div>
            </div>
        </section>

        <section class="zz-settings-card">
            <div class="zz-settings-header">
                <div>
                    <h2 class="zz-settings-title">Branding</h2>
                    <p class="zz-settings-desc">Upload separate logos for light and dark backgrounds.</p>
                </div>
            </div>

            <div class="zz-settings-grid">
                <div class="zz-field">
                    <label for="company-logo">Light Logo</label>
                    <div class="zz-logo-row">
                        <div class="zz-logo-preview">
                            @if ($logoUpload)
                                <img src="{{ $logoUpload->temporaryUrl() }}" alt="Logo preview">
                            @elseif ($this->logoUrl())
                                <img src="{{ $this->logoUrl() }}" alt="Company logo">
                            @else
                                No light logo
                            @endif
                        </div>
                        <div class="zz-logo-column" style="flex: 1;">
                            <input id="company-logo" class="zz-input" type="file" wire:model="logoUpload" accept="image/*">
                            @error('logoUpload') <span class="zz-error">{{ $message }}</span> @enderror
                            @if ($logo)
                                <button type="button" class="zz-button zz-button-secondary" wire:click="removeLogo('light')" wire:loading.attr="disabled">Remove Light Logo</button>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="zz-field">
                    <label for="company-dark-logo">Dark Logo</label>
                    <div class="zz-logo-row">
                        <div class="zz-logo-preview zz-logo-preview-dark">
                            @if ($darkLogoUpload)
                                <img src="{{ $darkLogoUpload->temporaryUrl() }}" alt="Dark logo preview">
                            @elseif ($this->darkLogoUrl())
                                <img src="{{ $this->darkLogoUrl() }}" alt="Company dark logo">
                            @else
                                No dark logo
                            @endif
                        </div>
                        <div class="zz-logo-column" style="flex: 1;">
                            <input id="company-dark-logo" class="zz-input" type="file" wire:model="darkLogoUpload" accept="image/*">
                            @error('darkLogoUpload') <span class="zz-error">{{ $message }}</span> @enderror
                            @if ($darkLogo)
                                <button type="button" class="zz-button zz-button-secondary" wire:click="removeLogo('dark')" wire:loading.attr="disabled">Remove Dark Logo</button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="zz-actions">
                <button type="submit" class="zz-button" wire:loading.attr="disabled" wire:target="logoUpload,darkLogoUpload,save">Save Settings</button>
            </div>
        </section>
    </form>
</x-filament-panels::page>
