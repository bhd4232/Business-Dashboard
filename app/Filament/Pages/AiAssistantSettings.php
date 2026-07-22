<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Crm;
use App\Models\Company;
use App\Services\CompanyContext;
use App\Services\Crm\AiSettingsService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class AiAssistantSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?string $cluster = Crm::class;

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'AI Assistant';

    protected static ?string $title = 'AI Assistant Settings';

    protected string $view = 'filament.pages.ai-assistant-settings';

    public array $settings = [];

    public static function canAccess(): bool
    {
        return Auth::user()?->isSuperAdmin() ?? false;
    }

    public function mount(AiSettingsService $service): void
    {
        $company = $this->company();
        abort_unless($company !== null, 404);

        $this->settings = $service->all($company);
        $this->settings['api_key'] = ''; // never round-trip the stored key to the browser
        $this->settings['has_api_key'] = filled($service->all($company)['api_key']);
    }

    public function save(AiSettingsService $service): void
    {
        $this->validate([
            'settings.enabled' => ['boolean'],
            'settings.provider' => ['required', 'in:anthropic,openai'],
            'settings.model' => ['required', 'string', 'max:100'],
            'settings.confidence_threshold' => ['required', 'numeric', 'min:0', 'max:1'],
            'settings.max_consecutive_ai_replies' => ['required', 'integer', 'min:1', 'max:20'],
            'settings.brand_voice' => ['nullable', 'string', 'max:2000'],
            'settings.api_key' => ['nullable', 'string', 'max:500'],
        ]);

        $company = $this->company();
        abort_unless($company !== null, 404);

        $service->save($company, $this->settings);

        $this->settings = $service->all($company->fresh());
        $this->settings['has_api_key'] = filled($this->settings['api_key']);
        $this->settings['api_key'] = '';

        Notification::make()->title('AI assistant settings saved')->success()->send();
    }

    /** Falls back to the default company when "All Companies" is selected. */
    protected function company(): ?Company
    {
        return app(CompanyContext::class)->company() ?? Company::defaultCompany();
    }
}
