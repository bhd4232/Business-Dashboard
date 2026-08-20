<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Settings;
use App\Support\FirebaseSettings;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use JsonException;

class PushNotificationSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static ?string $cluster = Settings::class;

    protected static ?string $navigationLabel = 'Push Notifications';

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'Push Notification Settings';

    protected string $view = 'filament.pages.push-notification-settings';

    /** @var array<string, mixed> */
    public array $settings = [];

    public static function canAccess(): bool
    {
        return FirebaseSettings::schemaIsReady() && (Auth::user()?->isSuperAdmin() ?? false);
    }

    public function mount(): void
    {
        $this->settings = FirebaseSettings::all();
        // Never round-trip the stored secret to the browser -- same
        // pattern as AiAssistantSettings/ConnectWhatsAppBusinessApp.
        $this->settings['has_service_account'] = filled($this->settings['service_account_json'] ?? null);
        $this->settings['service_account_json'] = '';
        $this->settings['has_vapid_key'] = filled($this->settings['vapid_key'] ?? null);
        $this->settings['vapid_key'] = '';
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->validate([
            'settings.enabled' => ['boolean'],
            'settings.api_key' => ['nullable', 'string', 'max:191'],
            'settings.auth_domain' => ['nullable', 'string', 'max:191'],
            'settings.project_id' => ['nullable', 'string', 'max:191'],
            'settings.storage_bucket' => ['nullable', 'string', 'max:191'],
            'settings.messaging_sender_id' => ['nullable', 'string', 'max:191'],
            'settings.app_id' => ['nullable', 'string', 'max:191'],
            'settings.vapid_key' => ['nullable', 'string', 'max:500'],
            'settings.service_account_json' => ['nullable', 'string', 'max:20000'],
        ]);

        $serviceAccountJson = trim((string) ($this->settings['service_account_json'] ?? ''));

        if ($serviceAccountJson !== '') {
            try {
                $decoded = json_decode($serviceAccountJson, true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                $this->addError('settings.service_account_json', 'The service account credentials must be valid JSON.');

                return;
            }

            if (! is_array($decoded) || blank($decoded['client_email'] ?? null) || blank($decoded['private_key'] ?? null)) {
                $this->addError('settings.service_account_json', 'The service account JSON is missing client_email/private_key.');

                return;
            }
        }

        $toSave = $this->settings;

        // Blank fields mean "leave the previously saved secret unchanged" --
        // only overwrite when a new value was actually entered.
        if ($serviceAccountJson === '') {
            unset($toSave['service_account_json']);
        } else {
            $toSave['service_account_json'] = $serviceAccountJson;
        }

        $vapidKey = trim((string) ($this->settings['vapid_key'] ?? ''));

        if ($vapidKey === '') {
            unset($toSave['vapid_key']);
        } else {
            $toSave['vapid_key'] = $vapidKey;
        }

        FirebaseSettings::save($toSave);

        $this->settings = FirebaseSettings::all();
        $this->settings['has_service_account'] = filled($this->settings['service_account_json'] ?? null);
        $this->settings['service_account_json'] = '';
        $this->settings['has_vapid_key'] = filled($this->settings['vapid_key'] ?? null);
        $this->settings['vapid_key'] = '';

        Notification::make()
            ->title('Push notification settings saved')
            ->success()
            ->send();
    }
}
