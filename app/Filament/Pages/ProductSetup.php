<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Settings;
use App\Services\CompanySettingsService;
use App\Services\LicenseActivationService;
use App\Services\ProductSetupService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class ProductSetup extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRocketLaunch;

    protected static ?string $cluster = Settings::class;

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Product Setup';

    protected string $view = 'filament.pages.product-setup';

    public string $companyName = '';

    public ?string $companyEmail = null;

    public string $currency = 'BDT';

    public string $timezone = 'Asia/Dhaka';

    public string $dateFormat = 'd M Y';

    public bool $onboardingCompleted = false;

    public bool $demoMode = false;

    public string $demoNotice = '';

    public ?string $licenseKey = null;

    public ?string $licensedTo = null;

    public ?string $supportEmail = null;

    public function mount(CompanySettingsService $company, ProductSetupService $setup, LicenseActivationService $licenses): void
    {
        $profile = $company->profile();
        $license = $licenses->details();

        $this->companyName = (string) $profile['name'];
        $this->companyEmail = $profile['email'];
        $this->currency = (string) $profile['currency'];
        $this->timezone = (string) $profile['timezone'];
        $this->dateFormat = (string) $profile['date_format'];
        $this->onboardingCompleted = $setup->onboardingCompleted();
        $this->demoMode = $setup->demoMode();
        $this->demoNotice = $setup->demoNotice();
        $this->licenseKey = null;
        $this->licensedTo = (string) $license['licensed_to'];
        $this->supportEmail = (string) $license['support_email'];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make([
                    'default' => 1,
                    'lg' => 3,
                ])
                    ->schema([
                        Section::make('Onboarding Wizard')
                            ->description('Finish the client-ready setup after installation.')
                            ->icon(Heroicon::OutlinedRocketLaunch)
                            ->columnSpan([
                                'default' => 1,
                                'lg' => 2,
                            ])
                            ->schema([
                                TextInput::make('companyName')
                                    ->label('Company Name')
                                    ->required()
                                    ->maxLength(120)
                                    ->autocomplete('organization'),
                                TextInput::make('companyEmail')
                                    ->label('Company Email')
                                    ->email()
                                    ->maxLength(120)
                                    ->autocomplete('email'),
                                TextInput::make('currency')
                                    ->label('Currency')
                                    ->required()
                                    ->maxLength(12)
                                    ->autocomplete(false),
                                Select::make('timezone')
                                    ->label('Timezone')
                                    ->options($this->timezoneOptions())
                                    ->required()
                                    ->searchable(),
                                Select::make('dateFormat')
                                    ->label('Date Format')
                                    ->options($this->dateFormatOptions())
                                    ->required(),
                                Toggle::make('onboardingCompleted')
                                    ->label('Mark onboarding complete'),
                                Toggle::make('demoMode')
                                    ->label('Enable demo mode'),
                                TextInput::make('demoNotice')
                                    ->label('Demo Notice')
                                    ->maxLength(180)
                                    ->autocomplete(false)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->footerActions([
                                Action::make('saveProductSetup')
                                    ->label('Save Setup')
                                    ->icon(Heroicon::OutlinedCheckCircle)
                                    ->action(fn () => $this->saveSetup()),
                            ]),

                        Section::make('Setup Checklist')
                            ->description('A quick product-readiness snapshot.')
                            ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                            ->columnSpan([
                                'default' => 1,
                                'lg' => 1,
                            ])
                            ->schema([
                                RepeatableEntry::make('setupChecklist')
                                    ->hiddenLabel()
                                    ->state(fn (): array => $this->setupChecklist())
                                    ->table([
                                        TableColumn::make('Item'),
                                        TableColumn::make('Status'),
                                    ])
                                    ->schema([
                                        TextEntry::make('label'),
                                        TextEntry::make('done')
                                            ->formatStateUsing(fn (bool $state): string => $state ? 'Done' : 'Open')
                                            ->badge()
                                            ->color(fn (bool $state): string => $state ? 'success' : 'warning'),
                                    ]),
                            ]),

                        Section::make('License Activation')
                            ->description('Activate this installation and keep support contact details up to date.')
                            ->icon(Heroicon::OutlinedKey)
                            ->columnSpanFull()
                            ->schema([
                                TextEntry::make('licenseStatus')
                                    ->label('Current Status')
                                    ->state(fn (): string => $this->licenseDetails()['is_active'] ? 'Active' : 'Not activated')
                                    ->badge()
                                    ->color(fn (): string => $this->licenseDetails()['is_active'] ? 'success' : 'warning'),
                                TextEntry::make('maskedLicenseKey')
                                    ->label('License Key')
                                    ->state(fn (): string => (string) $this->licenseDetails()['masked_key']),
                                TextInput::make('licenseKey')
                                    ->label('New License Key')
                                    ->required()
                                    ->maxLength(32)
                                    ->placeholder('ZZERP-XXXX-XXXX-XXXX')
                                    ->autocomplete(false),
                                TextInput::make('licensedTo')
                                    ->label('Licensed To')
                                    ->required()
                                    ->maxLength(120)
                                    ->autocomplete('organization'),
                                TextInput::make('supportEmail')
                                    ->label('Support Email')
                                    ->email()
                                    ->maxLength(120)
                                    ->autocomplete('email'),
                            ])
                            ->columns(3)
                            ->footerActions([
                                Action::make('activateProductLicense')
                                    ->label('Activate License')
                                    ->icon(Heroicon::OutlinedKey)
                                    ->action(fn () => $this->activateLicense()),
                            ]),
                    ]),
            ]);
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->canManageSettings() ?? false;
    }

    public function saveSetup(): void
    {
        $this->validate([
            'companyName' => ['required', 'string', 'max:120'],
            'companyEmail' => ['nullable', 'email', 'max:120'],
            'currency' => ['required', 'string', 'max:12'],
            'timezone' => ['required', 'timezone'],
            'dateFormat' => ['required', 'string', 'max:30'],
            'demoNotice' => ['nullable', 'string', 'max:180'],
        ]);

        app(CompanySettingsService::class)->save([
            'name' => $this->companyName,
            'email' => $this->companyEmail,
            'currency' => $this->currency,
            'timezone' => $this->timezone,
            'date_format' => $this->dateFormat,
            'logo' => app(CompanySettingsService::class)->profile()['logo'],
            'dark_logo' => app(CompanySettingsService::class)->profile()['dark_logo'],
            'address' => app(CompanySettingsService::class)->profile()['address'],
            'phone' => app(CompanySettingsService::class)->profile()['phone'],
        ]);

        app(ProductSetupService::class)->save([
            'installed' => true,
            'onboarding_completed' => $this->onboardingCompleted,
            'demo_mode' => $this->demoMode,
            'demo_notice' => $this->demoNotice,
        ]);

        Notification::make()
            ->title('Product setup saved')
            ->success()
            ->send();
    }

    public function activateLicense(): void
    {
        $this->validate([
            'licenseKey' => ['required', 'string', 'max:32'],
            'licensedTo' => ['required', 'string', 'max:120'],
            'supportEmail' => ['nullable', 'email', 'max:120'],
        ]);

        $activated = app(LicenseActivationService::class)->activate([
            'key' => $this->licenseKey,
            'licensed_to' => $this->licensedTo,
            'support_email' => $this->supportEmail,
        ]);

        if (! $activated) {
            Notification::make()
                ->title('Invalid license key')
                ->body('Use the format ZZERP-XXXX-XXXX-XXXX.')
                ->danger()
                ->send();

            return;
        }

        $this->licenseKey = null;

        Notification::make()
            ->title('License activated')
            ->success()
            ->send();
    }

    public function licenseDetails(): array
    {
        return app(LicenseActivationService::class)->details();
    }

    public function setupChecklist(): array
    {
        $company = app(CompanySettingsService::class)->profile();
        $license = app(LicenseActivationService::class)->details();

        return [
            ['label' => 'Company profile configured', 'done' => filled($company['name'])],
            ['label' => 'Light or dark logo uploaded', 'done' => filled($company['logo']) || filled($company['dark_logo'])],
            ['label' => 'Currency and timezone reviewed', 'done' => filled($company['currency']) && filled($company['timezone'])],
            ['label' => 'License activated', 'done' => $license['is_active']],
            ['label' => 'Onboarding marked complete', 'done' => $this->onboardingCompleted],
        ];
    }

    public function dateFormatOptions(): array
    {
        return [
            'd M Y' => now()->format('d M Y'),
            'd/m/Y' => now()->format('d/m/Y'),
            'Y-m-d' => now()->format('Y-m-d'),
            'M d, Y' => now()->format('M d, Y'),
        ];
    }

    public function timezoneOptions(): array
    {
        return [
            'Asia/Dhaka' => 'Asia/Dhaka',
            'UTC' => 'UTC',
            'Asia/Dubai' => 'Asia/Dubai',
            'Asia/Kolkata' => 'Asia/Kolkata',
            'Asia/Shanghai' => 'Asia/Shanghai',
            'Europe/London' => 'Europe/London',
            'America/New_York' => 'America/New_York',
        ];
    }
}
