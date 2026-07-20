<x-filament-panels::page>
    <style>
        .zz-storage {
            --zz-card-bg: #ffffff;
            --zz-card-border: #e5e7eb;
            --zz-header-bg: #f8fafc;
            --zz-header-border: #e5e7eb;
            --zz-title: #111827;
            --zz-text: #374151;
            --zz-muted: #64748b;
            --zz-field-bg: #ffffff;
            --zz-field-border: #cbd5e1;
            --zz-secondary-bg: #f8fafc;
            --zz-secondary-hover: #fff7ed;
            --zz-guide-bg: #f8fafc;
            --zz-warning-text: #92400e;
            --zz-warning-bg: #fef3c7;
            --zz-ready-text: #047857;
            --zz-ready-bg: #d1fae5;

            display: grid;
            gap: 18px;
        }

        .dark .zz-storage {
            --zz-card-bg: #17181c;
            --zz-card-border: #2b2d33;
            --zz-header-bg: #101827;
            --zz-header-border: #283244;
            --zz-title: #f7f8fb;
            --zz-text: #e5e7eb;
            --zz-muted: #a8adb8;
            --zz-field-bg: #222329;
            --zz-field-border: #3a3d45;
            --zz-secondary-bg: #222329;
            --zz-secondary-hover: #26282f;
            --zz-guide-bg: #121923;
            --zz-warning-text: #fcd34d;
            --zz-warning-bg: #46320b;
            --zz-ready-text: #7ee6b8;
            --zz-ready-bg: #063c2c;
        }

        .zz-storage-card {
            background: var(--zz-card-bg);
            border: 1px solid var(--zz-card-border);
            border-radius: 10px;
            overflow: hidden;
        }

        .zz-storage-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 16px;
            background: var(--zz-header-bg);
            border-bottom: 1px solid var(--zz-header-border);
        }

        .zz-storage-footer {
            border-top: 1px solid var(--zz-header-border);
            border-bottom: 0;
            flex-wrap: wrap;
        }

        .zz-storage-title {
            margin: 0;
            color: var(--zz-title);
            font-size: 18px;
            font-weight: 850;
        }

        .zz-storage-desc {
            margin: 5px 0 0;
            max-width: 760px;
            color: var(--zz-muted);
            font-size: 13px;
        }

        .zz-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 40px;
            padding: 0 14px;
            color: #111827;
            background: #f59e0b;
            border: 1px solid #f59e0b;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 800;
            cursor: pointer;
            white-space: nowrap;
        }

        .zz-button:hover {
            background: #fbbf24;
            border-color: #fbbf24;
        }

        .zz-button-secondary {
            color: var(--zz-title);
            background: var(--zz-secondary-bg);
            border-color: var(--zz-field-border);
        }

        .zz-button-secondary:hover {
            color: #f59e0b;
            background: var(--zz-secondary-hover);
            border-color: #f59e0b;
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
            color: var(--zz-title);
            font-size: 13px;
            font-weight: 800;
        }

        .zz-help {
            color: var(--zz-muted);
            font-size: 12px;
            line-height: 1.45;
        }

        .zz-input {
            width: 100%;
            height: 40px;
            padding: 0 12px;
            color: var(--zz-title);
            background: var(--zz-field-bg);
            border: 1px solid var(--zz-field-border);
            border-radius: 8px;
            outline: none;
        }

        .zz-checkbox {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            color: var(--zz-title);
            font-size: 14px;
            font-weight: 800;
        }

        .zz-checkbox input {
            width: 18px;
            height: 18px;
            accent-color: #f59e0b;
        }

        .zz-status-pill {
            display: inline-flex;
            align-items: center;
            min-height: 28px;
            padding: 0 10px;
            color: var(--zz-warning-text);
            background: var(--zz-warning-bg);
            border-radius: 8px;
            font-size: 12px;
            font-weight: 850;
        }

        .zz-status-pill.is-ready {
            color: var(--zz-ready-text);
            background: var(--zz-ready-bg);
        }

        .zz-guide {
            display: grid;
            gap: 10px;
            padding: 16px;
            color: var(--zz-text);
            background: var(--zz-guide-bg);
            border-bottom: 1px solid var(--zz-header-border);
            font-size: 13px;
            line-height: 1.55;
        }

        .zz-guide h3 {
            margin: 0;
            color: var(--zz-title);
            font-size: 15px;
            font-weight: 850;
        }

        .zz-guide ol {
            display: grid;
            gap: 7px;
            margin: 0;
            padding-left: 20px;
        }

        .zz-guide code {
            color: #fcd34d;
            font-size: 12px;
        }

        @media (max-width: 768px) {
            .zz-storage-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .zz-settings-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="zz-storage">
        <div class="zz-storage-card">
            <div class="zz-storage-header">
                <div>
                    <h2 class="zz-storage-title">Cloudflare R2 File Storage</h2>
                    <p class="zz-storage-desc">
                        Product images, category/company logos, and storefront banners are stored on this server's disk by default.
                        Connect a Cloudflare R2 bucket here to move uploads to R2 instead — free egress, and files survive server
                        redeploys. Nothing changes until you enable it below and save.
                    </p>
                </div>

                <span class="zz-status-pill {{ $this->isConfigured() ? 'is-ready' : '' }}">
                    {{ $this->isConfigured() ? 'Configured' : 'Not configured' }}
                </span>
            </div>

            <div class="zz-guide">
                <h3>Where to get these values</h3>
                <ol>
                    <li>In the Cloudflare dashboard, open <code>R2 Object Storage</code> and create a bucket (e.g. <code>zamzam-storefront</code>).</li>
                    <li>Turn on public access for the bucket (or attach a custom domain) — this is what you'll paste as <strong>Public URL</strong> below.</li>
                    <li>Go to <code>R2 &gt; Manage API Tokens</code>, create a token with <code>Object Read &amp; Write</code> permission scoped to this bucket.</li>
                    <li>Copy the <strong>Access Key ID</strong>, <strong>Secret Access Key</strong>, and <strong>S3 Endpoint</strong> shown after creating the token.</li>
                    <li>Paste all five values below, save, then click <strong>Test connection</strong> before enabling it for real uploads.</li>
                </ol>
            </div>

            <form wire:submit.prevent="save">
                <div class="zz-settings-grid">
                    <label class="zz-checkbox zz-field-full">
                        <input type="checkbox" wire:model="enabled" />
                        Enable Cloudflare R2 storage (uploads and asset URLs will use R2 instead of local disk)
                    </label>

                    <div class="zz-field">
                        <label for="accessKeyId">Access Key ID</label>
                        <input id="accessKeyId" class="zz-input" type="text" wire:model.defer="accessKeyId" placeholder="R2 access key ID" autocomplete="off">
                    </div>

                    <div class="zz-field">
                        <label for="secretAccessKey">Secret Access Key</label>
                        <input id="secretAccessKey" class="zz-input" type="password" wire:model.defer="secretAccessKey" placeholder="Leave blank to keep the saved key" autocomplete="new-password">
                        <div class="zz-help">
                            {{ $this->hasStoredSecretAccessKey() ? 'A secret key is already saved securely. Paste a new one only if you want to replace it.' : 'No secret key saved yet.' }}
                        </div>
                    </div>

                    <div class="zz-field">
                        <label for="bucket">Bucket name</label>
                        <input id="bucket" class="zz-input" type="text" wire:model.defer="bucket" placeholder="zamzam-storefront" autocomplete="off">
                    </div>

                    <div class="zz-field">
                        <label for="endpoint">S3 Endpoint</label>
                        <input id="endpoint" class="zz-input" type="text" wire:model.defer="endpoint" placeholder="https://<account_id>.r2.cloudflarestorage.com" autocomplete="off">
                    </div>

                    <div class="zz-field zz-field-full">
                        <label for="publicUrl">Public URL</label>
                        <input id="publicUrl" class="zz-input" type="text" wire:model.defer="publicUrl" placeholder="https://pub-xxxxxxxx.r2.dev or your custom domain" autocomplete="off">
                        <div class="zz-help">This is what customers' browsers actually load images from — it must be publicly reachable.</div>
                    </div>
                </div>

                <div class="zz-storage-header zz-storage-footer">
                    <p class="zz-storage-desc">Credentials are encrypted before saving to the database. Save first, then test the connection.</p>

                    <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                        <button type="button" class="zz-button zz-button-secondary" wire:click="testConnection" wire:loading.attr="disabled">
                            <x-filament::icon icon="heroicon-m-signal" />
                            Test connection
                        </button>

                        <button type="submit" class="zz-button">
                            <x-filament::icon icon="heroicon-m-check" />
                            Save
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-filament-panels::page>
