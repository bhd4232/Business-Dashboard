<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Settings;
use App\Services\CoolifyDeploymentService;
use App\Support\CoolifySettings;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Where a super admin plugs in this app's own Coolify credentials (instance
 * URL, API token, Application UUID) -- see App\Support\CoolifySettings.
 * Powers App\Filament\Pages\AppVersions' "rollback to a previous
 * deployment" button.
 */
class CoolifyDeploymentSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static ?string $cluster = Settings::class;

    protected static ?string $navigationLabel = 'Deployment Settings';

    protected static ?int $navigationSort = 9;

    protected static ?string $title = 'Deployment Settings';

    protected string $view = 'filament.pages.coolify-deployment-settings';

    /** @var array<string, mixed> */
    public array $settings = [];

    public static function canAccess(): bool
    {
        return CoolifySettings::schemaIsReady() && (Auth::user()?->isSuperAdmin() ?? false);
    }

    public function mount(): void
    {
        $this->settings = CoolifySettings::all();
        // Never round-trip the stored secrets to the browser -- same
        // pattern as PushNotificationSettings/AiAssistantSettings.
        $this->settings['has_api_token'] = filled($this->settings['api_token'] ?? null);
        $this->settings['api_token'] = '';
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->validate([
            'settings.enabled' => ['boolean'],
            'settings.base_url' => ['nullable', 'string', 'max:255', 'url'],
            'settings.api_token' => ['nullable', 'string', 'max:500'],
            'settings.application_uuid' => ['nullable', 'string', 'max:191'],
        ]);

        $toSave = $this->settings;

        // Blank means "leave the previously saved token unchanged" -- only
        // overwrite when a new value was actually entered.
        $apiToken = trim((string) ($this->settings['api_token'] ?? ''));

        if ($apiToken === '') {
            unset($toSave['api_token']);
        } else {
            $toSave['api_token'] = $apiToken;
        }

        CoolifySettings::save($toSave);

        $this->settings = CoolifySettings::all();
        $this->settings['has_api_token'] = filled($this->settings['api_token'] ?? null);
        $this->settings['api_token'] = '';

        Notification::make()
            ->title('Deployment settings saved')
            ->success()
            ->send();
    }

    public function testConnection(): void
    {
        abort_unless(static::canAccess(), 403);

        if (! CoolifySettings::isConfigured()) {
            Notification::make()
                ->title('Fill in and save every field first')
                ->warning()
                ->send();

            return;
        }

        try {
            $candidates = app(CoolifyDeploymentService::class)->rollbackCandidates(1);
        } catch (Throwable $exception) {
            Notification::make()
                ->title('Could not reach Coolify')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('Connected to Coolify successfully')
            ->body($candidates === []
                ? 'No earlier finished deployment was found yet to roll back to -- that\'s expected on a brand-new application.'
                : 'Found '.count($candidates).' earlier deployment(s) available for rollback.')
            ->success()
            ->send();
    }
}
