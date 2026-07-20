<?php

namespace App\Filament\Pages;

use App\Services\StorageSettingsService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class CloudStorageSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCloudArrowUp;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Cloud Storage';

    protected static ?string $title = 'Cloud Storage (Cloudflare R2)';

    protected string $view = 'filament.pages.cloud-storage-settings';

    public bool $enabled = false;

    public ?string $accessKeyId = null;

    public ?string $secretAccessKey = null;

    public ?string $bucket = null;

    public ?string $endpoint = null;

    public ?string $publicUrl = null;

    public function mount(StorageSettingsService $settings): void
    {
        $this->enabled = $settings->enabled();
        $this->accessKeyId = $settings->accessKeyId();
        $this->secretAccessKey = null;
        $this->bucket = $settings->bucket();
        $this->endpoint = $settings->endpoint();
        $this->publicUrl = $settings->publicUrl();
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->canManageSettings() ?? false;
    }

    public function save(StorageSettingsService $settings): void
    {
        $settings->save([
            'enabled' => $this->enabled,
            'access_key_id' => $this->accessKeyId,
            'secret_access_key' => $this->secretAccessKey,
            'bucket' => $this->bucket,
            'endpoint' => $this->endpoint,
            'public_url' => $this->publicUrl,
        ]);

        $this->secretAccessKey = null;

        Notification::make()
            ->title('Cloud storage settings saved')
            ->body($this->enabled
                ? 'Product images, logos, and storefront banners will now upload to and serve from Cloudflare R2.'
                : 'Cloud storage is disabled — uploads will keep using local server disk.')
            ->success()
            ->send();
    }

    /**
     * Tests the credentials as currently saved in the database, not
     * whatever is sitting unsaved in the form — the secret key field is
     * blank-means-keep-existing (see StorageSettingsService::save()), so
     * there's no reliable "unsaved secret" to test against. Save first,
     * then test; the UI hint below the button says so.
     */
    public function testConnection(StorageSettingsService $settings): void
    {
        $result = $settings->testConnection();

        $notification = Notification::make()
            ->title($result['ok'] ? 'Connection successful' : 'Connection failed')
            ->body($result['message']);

        $result['ok'] ? $notification->success()->send() : $notification->danger()->send();
    }

    public function hasStoredSecretAccessKey(): bool
    {
        return app(StorageSettingsService::class)->hasSecretAccessKey();
    }

    public function isConfigured(): bool
    {
        return app(StorageSettingsService::class)->isConfigured();
    }
}
