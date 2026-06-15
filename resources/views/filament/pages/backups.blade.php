<x-filament-panels::page>
    <style>
        .zz-backups {
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
            --zz-modal-backdrop: rgb(15 23 42 / .42);
            --zz-modal-shadow: 0 24px 70px rgb(15 23 42 / .24);
            --zz-guide-bg: #f8fafc;
            --zz-table-head-bg: #f8fafc;
            --zz-table-row-border: #e5e7eb;
            --zz-warning-text: #92400e;
            --zz-warning-bg: #fef3c7;
            --zz-ready-text: #047857;
            --zz-ready-bg: #d1fae5;

            display: grid;
            gap: 18px;
        }

        .dark .zz-backups {
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
            --zz-modal-backdrop: rgb(0 0 0 / .62);
            --zz-modal-shadow: 0 24px 70px rgb(0 0 0 / .46);
            --zz-guide-bg: #121923;
            --zz-table-head-bg: #1d1f25;
            --zz-table-row-border: #25272d;
            --zz-warning-text: #fcd34d;
            --zz-warning-bg: #46320b;
            --zz-ready-text: #7ee6b8;
            --zz-ready-bg: #063c2c;
        }

        .zz-backup-card {
            background: var(--zz-card-bg);
            border: 1px solid var(--zz-card-border);
            border-radius: 10px;
            overflow: hidden;
        }

        .zz-backup-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 16px;
            background: var(--zz-header-bg);
            border-bottom: 1px solid var(--zz-header-border);
        }

        .zz-backup-footer {
            border-top: 1px solid var(--zz-header-border);
            border-bottom: 0;
        }

        .zz-backup-copy {
            min-width: 0;
        }

        .zz-backup-title {
            margin: 0;
            color: var(--zz-title);
            font-size: 18px;
            font-weight: 850;
        }

        .zz-backup-desc {
            margin: 5px 0 0;
            color: var(--zz-muted);
            font-size: 13px;
        }

        .zz-backup-desc-narrow {
            max-width: 760px;
        }

        .zz-backup-actions {
            display: flex;
            flex: 0 0 auto;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            white-space: nowrap;
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
            text-decoration: none;
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

        .zz-input,
        .zz-textarea {
            width: 100%;
            color: var(--zz-title);
            background: var(--zz-field-bg);
            border: 1px solid var(--zz-field-border);
            border-radius: 8px;
            outline: none;
        }

        .zz-input {
            height: 40px;
            padding: 0 12px;
        }

        .zz-textarea {
            min-height: 150px;
            padding: 12px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
            font-size: 12px;
            resize: vertical;
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

        .zz-modal-backdrop {
            position: fixed;
            inset: 0;
            z-index: 60;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: var(--zz-modal-backdrop);
        }

        .zz-modal {
            width: min(980px, 100%);
            max-height: min(88vh, 860px);
            overflow: hidden;
            color: var(--zz-text);
            background: var(--zz-card-bg);
            border: 1px solid var(--zz-card-border);
            border-radius: 12px;
            box-shadow: var(--zz-modal-shadow);
        }

        .zz-modal-body {
            max-height: calc(min(88vh, 860px) - 74px);
            overflow-y: auto;
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

        .zz-table {
            width: 100%;
            border-collapse: collapse;
            color: var(--zz-text);
            font-size: 14px;
        }

        .zz-table th {
            padding: 11px 14px;
            color: var(--zz-muted);
            background: var(--zz-table-head-bg);
            border-bottom: 1px solid var(--zz-card-border);
            font-size: 11px;
            font-weight: 850;
            letter-spacing: .05em;
            text-align: left;
            text-transform: uppercase;
        }

        .zz-table td {
            padding: 12px 14px;
            border-bottom: 1px solid var(--zz-table-row-border);
            vertical-align: middle;
        }

        .zz-code {
            color: var(--zz-title);
            font-weight: 800;
        }

        .zz-empty {
            padding: 28px 14px !important;
            color: var(--zz-muted);
            text-align: center;
        }

        @media (max-width: 768px) {
            .zz-backup-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .zz-backup-actions {
                justify-content: flex-start;
                white-space: normal;
            }

            .zz-settings-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="zz-backups">
        <div
            x-data="{ openDriveSettings: false }"
            x-on:keydown.escape.window="openDriveSettings = false"
            class="zz-backup-card"
        >
            <div class="zz-backup-header">
                <div>
                    <h2 class="zz-backup-title">Google Drive Backup</h2>
                    <p class="zz-backup-desc">Configure Drive credentials once, then upload full app backups manually or automatically.</p>
                </div>

                <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 10px;">
                    <span class="zz-status-pill {{ $this->googleDriveConfigured() ? 'is-ready' : '' }}">
                        {{ $this->googleDriveConfigured() ? 'Configured' : 'Not configured' }}
                    </span>

                    <button type="button" class="zz-button" x-on:click="openDriveSettings = true">
                        <x-filament::icon icon="heroicon-m-cog-6-tooth" />
                        Configure
                    </button>
                </div>
            </div>

            <div x-cloak x-show="openDriveSettings" class="zz-modal-backdrop" x-transition.opacity>
                <div class="zz-modal" x-on:click.outside="openDriveSettings = false">
                    <div class="zz-backup-header">
                        <div>
                            <h2 class="zz-backup-title">Google Drive Backup Settings</h2>
                            <p class="zz-backup-desc">Add service account credentials here to upload full app backups to Google Drive.</p>
                        </div>

                        <button type="button" class="zz-button zz-button-secondary" x-on:click="openDriveSettings = false">
                            Close
                        </button>
                    </div>

                    <div class="zz-modal-body">
                        <div class="zz-guide">
                            <h3>Where to get Service Account JSON and Folder ID</h3>
                            <ol>
                                <li>Open <code>console.cloud.google.com</code> and create/select a Google Cloud project.</li>
                                <li>Go to <code>APIs & Services > Library</code>, search <code>Google Drive API</code>, then enable it.</li>
                                <li>Go to <code>IAM & Admin > Service Accounts</code> and create a service account.</li>
                                <li>Open that service account, go to <code>Keys</code>, choose <code>Add key > Create new key > JSON</code>, then download the JSON file.</li>
                                <li>Open Google Drive, create/select a backup folder, open Share, and share it with the service account email from the JSON file.</li>
                                <li>Open the Drive folder. The folder ID is the long text after <code>/folders/</code> in the browser URL.</li>
                                <li>Paste the folder ID below, then either paste the full JSON content or upload/store the JSON file on the server and paste its absolute path.</li>
                            </ol>
                        </div>

                        <form wire:submit.prevent="saveGoogleDriveSettings">
                            <div class="zz-settings-grid">
                                <label class="zz-checkbox">
                                    <input type="checkbox" wire:model="googleDriveEnabled" />
                                    Enable Google Drive backup
                                </label>

                                <label class="zz-checkbox">
                                    <input type="checkbox" wire:model="googleDriveAutoUpload" />
                                    Auto upload daily
                                </label>

                                <div class="zz-field">
                                    <label for="googleDriveFolderId">Google Drive Folder ID</label>
                                    <input id="googleDriveFolderId" class="zz-input" type="text" wire:model.defer="googleDriveFolderId" placeholder="Drive folder ID" />
                                    <div class="zz-help">Example URL: drive.google.com/drive/folders/<strong>THIS_PART_IS_FOLDER_ID</strong></div>
                                </div>

                                <div class="zz-field">
                                    <label for="googleDriveServiceAccountPath">Service Account JSON Path</label>
                                    <input id="googleDriveServiceAccountPath" class="zz-input" type="text" wire:model.defer="googleDriveServiceAccountPath" placeholder="Optional absolute JSON file path" />
                                    <div class="zz-help">Optional. Use this if you keep the JSON file on the server.</div>
                                </div>

                                <div class="zz-field zz-field-full">
                                    <label for="googleDriveServiceAccountJson">Service Account JSON</label>
                                    <textarea id="googleDriveServiceAccountJson" class="zz-textarea" wire:model.defer="googleDriveServiceAccountJson" placeholder='Paste service account JSON here. Leave empty to keep the existing saved JSON.'></textarea>
                                    <div class="zz-help">
                                        {{ $this->hasStoredServiceAccountJson() ? 'A service account JSON is already saved securely. Paste a new one only if you want to replace it.' : 'No service account JSON is saved yet.' }}
                                    </div>
                                </div>
                            </div>

                            <div class="zz-backup-header zz-backup-footer">
                                <p class="zz-backup-desc">The JSON is encrypted before saving to the database.</p>
                                <button type="submit" class="zz-button">
                                    <x-filament::icon icon="heroicon-m-check" />
                                    Save Drive settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="zz-backup-card">
            <div class="zz-backup-header">
                <div class="zz-backup-copy">
                    <h2 class="zz-backup-title">Full App Backups</h2>
                    <p class="zz-backup-desc zz-backup-desc-narrow">Creates a ZIP archive with app files, public uploads, environment files, and a database backup. Google Drive upload requires service account configuration.</p>
                </div>

                <div class="zz-backup-actions">
                    <button type="button" class="zz-button" wire:click="createAppBackup" wire:loading.attr="disabled">
                        <x-filament::icon icon="heroicon-m-archive-box-arrow-down" />
                        Create app backup
                    </button>

                    <button type="button" class="zz-button zz-button-secondary" wire:click="createAndUploadAppBackup" wire:loading.attr="disabled" @disabled(! $this->googleDriveConfigured())>
                        <x-filament::icon icon="heroicon-m-cloud-arrow-up" />
                        Upload to Drive
                    </button>
                </div>
            </div>

            <div style="overflow-x: auto;">
                <table class="zz-table">
                    <thead>
                        <tr>
                            <th>File</th>
                            <th>Size</th>
                            <th>Created</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->appBackupFiles() as $backup)
                            <tr>
                                <td class="zz-code">{{ $backup['name'] }}</td>
                                <td>{{ $backup['size_human'] }}</td>
                                <td>{{ $backup['modified_label'] }}</td>
                                <td>
                                    <a href="{{ route('backups.download', $backup['name']) }}" class="zz-button zz-button-secondary">
                                        <x-filament::icon icon="heroicon-m-arrow-down-tray" />
                                        Download
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="zz-empty">No full app backups created yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="zz-backup-card">
            <div class="zz-backup-header">
                <div>
                    <h2 class="zz-backup-title">Database Backups</h2>
                    <p class="zz-backup-desc">Manual database backups are stored privately. The latest 10 backups are kept automatically.</p>
                </div>

                <button type="button" class="zz-button" wire:click="createBackup" wire:loading.attr="disabled">
                    <x-filament::icon icon="heroicon-m-circle-stack" />
                    Create backup
                </button>
            </div>

            <div style="overflow-x: auto;">
                <table class="zz-table">
                    <thead>
                        <tr>
                            <th>File</th>
                            <th>Size</th>
                            <th>Created</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->backupFiles() as $backup)
                            <tr>
                                <td class="zz-code">{{ $backup['name'] }}</td>
                                <td>{{ $backup['size_human'] }}</td>
                                <td>{{ $backup['modified_label'] }}</td>
                                <td>
                                    <a href="{{ route('backups.download', $backup['name']) }}" class="zz-button zz-button-secondary">
                                        <x-filament::icon icon="heroicon-m-arrow-down-tray" />
                                        Download
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="zz-empty">No backups created yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
