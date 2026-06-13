<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Schema;

class BackupSettingsService
{
    public const DRIVE_ENABLED = 'backup.google_drive.enabled';

    public const DRIVE_AUTO_UPLOAD = 'backup.google_drive.auto_upload';

    public const DRIVE_FOLDER_ID = 'backup.google_drive.folder_id';

    public const DRIVE_SERVICE_ACCOUNT_JSON = 'backup.google_drive.service_account_json';

    public const DRIVE_SERVICE_ACCOUNT_PATH = 'backup.google_drive.service_account_path';

    public function googleDriveEnabled(): bool
    {
        if (! $this->settingsTableExists()) {
            return (bool) config('backup.google_drive.enabled');
        }

        return AppSetting::boolValue(self::DRIVE_ENABLED, (bool) config('backup.google_drive.enabled'));
    }

    public function googleDriveAutoUpload(): bool
    {
        if (! $this->settingsTableExists()) {
            return (bool) config('backup.google_drive.auto_upload');
        }

        return AppSetting::boolValue(self::DRIVE_AUTO_UPLOAD, (bool) config('backup.google_drive.auto_upload'));
    }

    public function googleDriveFolderId(): ?string
    {
        if (! $this->settingsTableExists()) {
            return config('backup.google_drive.folder_id');
        }

        return AppSetting::getValue(self::DRIVE_FOLDER_ID, config('backup.google_drive.folder_id'));
    }

    public function serviceAccountJson(): ?string
    {
        if (! $this->settingsTableExists()) {
            return config('backup.google_drive.service_account_json');
        }

        return AppSetting::getValue(self::DRIVE_SERVICE_ACCOUNT_JSON, config('backup.google_drive.service_account_json'));
    }

    public function serviceAccountPath(): ?string
    {
        if (! $this->settingsTableExists()) {
            return config('backup.google_drive.service_account_path');
        }

        return AppSetting::getValue(self::DRIVE_SERVICE_ACCOUNT_PATH, config('backup.google_drive.service_account_path'));
    }

    public function hasServiceAccountJson(): bool
    {
        if (! $this->settingsTableExists()) {
            return filled(config('backup.google_drive.service_account_json'));
        }

        return filled(AppSetting::getValue(self::DRIVE_SERVICE_ACCOUNT_JSON));
    }

    public function saveGoogleDrive(array $data): void
    {
        AppSetting::setValue(self::DRIVE_ENABLED, ! empty($data['enabled']) ? '1' : '0');
        AppSetting::setValue(self::DRIVE_AUTO_UPLOAD, ! empty($data['auto_upload']) ? '1' : '0');
        AppSetting::setValue(self::DRIVE_FOLDER_ID, trim((string) ($data['folder_id'] ?? '')));
        AppSetting::setValue(self::DRIVE_SERVICE_ACCOUNT_PATH, trim((string) ($data['service_account_path'] ?? '')));

        if (filled($data['service_account_json'] ?? null)) {
            AppSetting::setValue(self::DRIVE_SERVICE_ACCOUNT_JSON, trim((string) $data['service_account_json']), encrypted: true);
        }
    }

    protected function settingsTableExists(): bool
    {
        return Schema::hasTable('app_settings');
    }
}
