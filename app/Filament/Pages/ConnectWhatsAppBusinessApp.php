<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Crm;
use App\Models\Company;
use App\Services\CompanyContext;
use App\Services\Meta\EmbeddedSignupService;
use App\Services\Meta\EmbeddedSignupSettingsService;
use App\Services\Meta\MetaGraphException;
use App\Services\Meta\MetaGraphService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class ConnectWhatsAppBusinessApp extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDevicePhoneMobile;

    protected static ?string $cluster = Crm::class;

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Connect WhatsApp App';

    protected static ?string $title = 'Connect WhatsApp Business App';

    protected string $view = 'filament.pages.connect-whats-app-business-app';

    public array $settings = [];

    public static function canAccess(): bool
    {
        return Auth::user()?->isSuperAdmin() ?? false;
    }

    public function mount(EmbeddedSignupSettingsService $service): void
    {
        $company = $this->company();
        abort_unless($company !== null, 404);

        $this->settings = $service->all($company);
        $this->settings['app_secret'] = ''; // never round-trip the stored secret to the browser
        $this->settings['has_app_secret'] = filled($service->all($company)['app_secret']);
        $this->settings['ready'] = $service->isConfigured($company);
        $this->settings['graph_api_version'] = app(MetaGraphService::class)->version();
    }

    public function save(EmbeddedSignupSettingsService $service): void
    {
        $this->validate([
            'settings.app_id' => ['required', 'string', 'max:100'],
            'settings.config_id' => ['required', 'string', 'max:100'],
            'settings.verify_token' => ['required', 'string', 'max:255'],
            'settings.app_secret' => ['nullable', 'string', 'max:255'],
        ]);

        $company = $this->company();
        abort_unless($company !== null, 404);

        $service->save($company, $this->settings);

        $fresh = $service->all($company->fresh());
        $this->settings = array_merge($this->settings, $fresh);
        $this->settings['has_app_secret'] = filled($fresh['app_secret']);
        $this->settings['app_secret'] = '';
        $this->settings['ready'] = $service->isConfigured($company->fresh());

        Notification::make()->title('Embedded Signup settings saved')->success()->send();
    }

    /**
     * Called from the page's JS once Meta's Embedded Signup popup finishes.
     * A normal same-origin Livewire call - CSRF-protected automatically, no
     * separate webhook-style route needed.
     */
    public function completeEmbeddedSignup(string $code, string $wabaId, string $phoneNumberId, ?string $businessName = null): void
    {
        $company = $this->company();
        abort_unless($company !== null, 404);

        try {
            $channel = app(EmbeddedSignupService::class)->complete($company, $code, $wabaId, $phoneNumberId, $businessName);
        } catch (MetaGraphException $exception) {
            Notification::make()->title('Could not complete the connection')->danger()->body($exception->getMessage())->send();

            return;
        }

        Notification::make()
            ->title('WhatsApp Business App connected')
            ->body("Number linked ({$channel->external_id}). Contacts and up to 180 days of chat history will sync in over the next few minutes.")
            ->success()
            ->send();
    }

    /** Falls back to the default company when "All Companies" is selected. */
    protected function company(): ?Company
    {
        return app(CompanyContext::class)->company() ?? Company::defaultCompany();
    }
}
