<?php

namespace App\Filament\Pages;

use App\Services\AppBackupService;
use App\Services\BackupSettingsService;
use App\Services\DatabaseBackupService;
use App\Services\GoogleDriveBackupService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Throwable;
use UnitEnum;

class Backups extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Backups';

    protected string $view = 'filament.pages.backups';

    public bool $googleDriveEnabled = false;

    public bool $googleDriveAutoUpload = false;

    public ?string $googleDriveFolderId = null;

    public ?string $googleDriveServiceAccountPath = null;

    public ?string $googleDriveServiceAccountJson = null;

    public function mount(BackupSettingsService $settings): void
    {
        $this->googleDriveEnabled = $settings->googleDriveEnabled();
        $this->googleDriveAutoUpload = $settings->googleDriveAutoUpload();
        $this->googleDriveFolderId = $settings->googleDriveFolderId();
        $this->googleDriveServiceAccountPath = $settings->serviceAccountPath();
        $this->googleDriveServiceAccountJson = null;
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->canManageBackups() ?? false;
    }

    public function createBackup(DatabaseBackupService $backups): void
    {
        try {
            $backup = $backups->create();

            Notification::make()
                ->title('Backup created')
                ->body("Created {$backup['name']}.")
                ->success()
                ->send();
        } catch (Throwable $exception) {
            Notification::make()
                ->title('Backup failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function createAppBackup(AppBackupService $backups): void
    {
        try {
            $backup = $backups->create(uploadToGoogleDrive: false);

            Notification::make()
                ->title('Full app backup created')
                ->body("Created {$backup['name']}.")
                ->success()
                ->send();
        } catch (Throwable $exception) {
            Notification::make()
                ->title('Full app backup failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function createAndUploadAppBackup(AppBackupService $backups): void
    {
        try {
            $backup = $backups->create(uploadToGoogleDrive: true);

            Notification::make()
                ->title('Full app backup uploaded')
                ->body("Created {$backup['name']} and uploaded it to Google Drive.")
                ->success()
                ->send();
        } catch (Throwable $exception) {
            Notification::make()
                ->title('Google Drive backup failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function saveGoogleDriveSettings(BackupSettingsService $settings): void
    {
        $json = trim((string) $this->googleDriveServiceAccountJson);

        if ($json !== '' && json_decode($json, true) === null) {
            Notification::make()
                ->title('Invalid service account JSON')
                ->body('Paste the full JSON from the Google Cloud service account key.')
                ->danger()
                ->send();

            return;
        }

        $settings->saveGoogleDrive([
            'enabled' => $this->googleDriveEnabled,
            'auto_upload' => $this->googleDriveAutoUpload,
            'folder_id' => $this->googleDriveFolderId,
            'service_account_path' => $this->googleDriveServiceAccountPath,
            'service_account_json' => $json,
        ]);

        $this->googleDriveServiceAccountJson = null;

        Notification::make()
            ->title('Google Drive settings saved')
            ->success()
            ->send();
    }

    public function backupFiles(): array
    {
        return app(DatabaseBackupService::class)->all();
    }

    public function appBackupFiles(): array
    {
        return app(AppBackupService::class)->all();
    }

    public function googleDriveConfigured(): bool
    {
        return app(GoogleDriveBackupService::class)->isConfigured();
    }

    public function hasStoredServiceAccountJson(): bool
    {
        return app(BackupSettingsService::class)->hasServiceAccountJson();
    }
}
