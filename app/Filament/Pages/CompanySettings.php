<?php

namespace App\Filament\Pages;

use App\Services\CompanySettingsService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use UnitEnum;

class CompanySettings extends Page
{
    use WithFileUploads;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Company Settings';

    protected string $view = 'filament.pages.company-settings';

    public string $name = '';

    public ?string $logo = null;

    public ?string $darkLogo = null;

    public ?TemporaryUploadedFile $logoUpload = null;

    public ?TemporaryUploadedFile $darkLogoUpload = null;

    public ?string $address = null;

    public ?string $phone = null;

    public ?string $email = null;

    public string $currency = 'BDT';

    public string $timezone = 'Asia/Dhaka';

    public string $dateFormat = 'd M Y';

    public function mount(): void
    {
        $settings = app(CompanySettingsService::class);
        $profile = $settings->profile();

        $this->name = (string) $profile['name'];
        $this->logo = $profile['logo'];
        $this->darkLogo = $profile['dark_logo'];
        $this->address = $profile['address'];
        $this->phone = $profile['phone'];
        $this->email = $profile['email'];
        $this->currency = (string) $profile['currency'];
        $this->timezone = (string) $profile['timezone'];
        $this->dateFormat = (string) $profile['date_format'];
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->canManageSettings() ?? false;
    }

    public function save(): void
    {
        $this->validate($this->rules());

        $this->storeUploadedLogos();
        $this->persistSettings();

        Notification::make()
            ->title('Company settings saved')
            ->success()
            ->send();
    }

    public function updatedLogoUpload(): void
    {
        $this->validateOnly('logoUpload', $this->rules());
        $this->storeUploadedLogos();
        $this->persistSettings();
    }

    public function updatedDarkLogoUpload(): void
    {
        $this->validateOnly('darkLogoUpload', $this->rules());
        $this->storeUploadedLogos();
        $this->persistSettings();
    }

    public function removeLogo(string $variant = 'light'): void
    {
        $settings = app(CompanySettingsService::class);

        if ($variant === 'dark') {
            $this->darkLogo = null;
        } else {
            $this->logo = null;
        }

        $settings->save([
            'name' => $this->name,
            'logo' => $this->logo,
            'dark_logo' => $this->darkLogo,
            'address' => $this->address,
            'phone' => $this->phone,
            'email' => $this->email,
            'currency' => $this->currency,
            'timezone' => $this->timezone,
            'date_format' => $this->dateFormat,
        ]);

        Notification::make()
            ->title($variant === 'dark' ? 'Dark logo removed' : 'Light logo removed')
            ->success()
            ->send();
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'logoUpload' => ['nullable', 'image', 'max:2048'],
            'darkLogoUpload' => ['nullable', 'image', 'max:2048'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:60'],
            'email' => ['nullable', 'email', 'max:120'],
            'currency' => ['required', 'string', 'max:12'],
            'timezone' => ['required', 'timezone'],
            'dateFormat' => ['required', 'string', 'max:30'],
        ];
    }

    protected function storeUploadedLogos(): void
    {
        if ($this->logoUpload) {
            $this->logo = $this->logoUpload->store('company', 'public');
            $this->logoUpload = null;
        }

        if ($this->darkLogoUpload) {
            $this->darkLogo = $this->darkLogoUpload->store('company', 'public');
            $this->darkLogoUpload = null;
        }
    }

    protected function persistSettings(): void
    {
        app(CompanySettingsService::class)->save([
            'name' => $this->name,
            'logo' => $this->logo,
            'dark_logo' => $this->darkLogo,
            'address' => $this->address,
            'phone' => $this->phone,
            'email' => $this->email,
            'currency' => $this->currency,
            'timezone' => $this->timezone,
            'date_format' => $this->dateFormat,
        ]);
    }

    public function logoUrl(): ?string
    {
        return app(CompanySettingsService::class)->logoUrl();
    }

    public function darkLogoUrl(): ?string
    {
        return app(CompanySettingsService::class)->darkLogoUrl(fallbackToLight: false);
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

    public function dateFormatOptions(): array
    {
        return [
            'd M Y' => now()->format('d M Y'),
            'd/m/Y' => now()->format('d/m/Y'),
            'Y-m-d' => now()->format('Y-m-d'),
            'M d, Y' => now()->format('M d, Y'),
        ];
    }
}
