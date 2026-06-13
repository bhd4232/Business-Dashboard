<x-filament-panels::page>
    <style>
        .zz-backups {
            display: grid;
            gap: 18px;
        }

        .zz-backup-card {
            background: #17181c;
            border: 1px solid #2b2d33;
            border-radius: 10px;
            overflow: hidden;
        }

        .zz-backup-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 16px;
            background: #101827;
            border-bottom: 1px solid #283244;
        }

        .zz-backup-copy {
            min-width: 0;
        }

        .zz-backup-title {
            margin: 0;
            color: #f7f8fb;
            font-size: 18px;
            font-weight: 850;
        }

        .zz-backup-desc {
            margin: 5px 0 0;
            color: #a8adb8;
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
            color: #f7f8fb;
            background: #222329;
            border-color: #3a3d45;
        }

        .zz-button-secondary:hover {
            color: #f59e0b;
            background: #26282f;
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
            color: #f7f8fb;
            font-size: 13px;
            font-weight: 800;
        }

        .zz-help {
            color: #9ca3af;
            font-size: 12px;
            line-height: 1.45;
        }

        .zz-input,
        .zz-textarea {
            width: 100%;
            color: #f7f8fb;
            background: #222329;
            border: 1px solid #3a3d45;
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
            color: #f7f8fb;
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
            color: #fcd34d;
            background: #46320b;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 850;
        }

        .zz-status-pill.is-ready {
            color: #7ee6b8;
            background: #063c2c;
        }

        .zz-modal-backdrop {
            position: fixed;
            inset: 0;
            z-index: 60;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgb(0 0 0 / .62);
        }

        .zz-modal {
            width: min(980px, 100%);
            max-height: min(88vh, 860px);
            overflow: hidden;
            color: #e5e7eb;
            background: #17181c;
            border: 1px solid #343741;
            border-radius: 12px;
            box-shadow: 0 24px 70px rgb(0 0 0 / .46);
        }

        .zz-modal-body {
            max-height: calc(min(88vh, 860px) - 74px);
            overflow-y: auto;
        }

        .zz-guide {
            display: grid;
            gap: 10px;
            padding: 16px;
            color: #d4d7de;
            background: #121923;
            border-bottom: 1px solid #283244;
            font-size: 13px;
            line-height: 1.55;
        }

        .zz-guide h3 {
            margin: 0;
            color: #f7f8fb;
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
            color: #e5e7eb;
            font-size: 14px;
        }

        .zz-table th {
            padding: 11px 14px;
            color: #9ca3af;
            background: #1d1f25;
            border-bottom: 1px solid #30333b;
            font-size: 11px;
            font-weight: 850;
            letter-spacing: .05em;
            text-align: left;
            text-transform: uppercase;
        }

        .zz-table td {
            padding: 12px 14px;
            border-bottom: 1px solid #25272d;
            vertical-align: middle;
        }

        .zz-code {
            color: #f7f8fb;
            font-weight: 800;
        }

        .zz-empty {
            padding: 28px 14px !important;
            color: #9ca3af;
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

                            <div class="zz-backup-header" style="border-top: 1px solid #283244; border-bottom: 0;">
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
