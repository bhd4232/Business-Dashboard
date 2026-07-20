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

    protected static ?string $title = 'ERP Settings';

    protected static ?string $navigationLabel = 'ERP Settings';

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

    public array $invoice = [];

    public string $insideAreas = '';

    public string $outsideAreas = '';

    public string $suburbAreas = '';

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
        $this->invoice = $settings->invoice();
        $this->insideAreas = implode(', ', $profile['shipping_zones']['inside'] ?? []);
        $this->outsideAreas = implode(', ', $profile['shipping_zones']['outside'] ?? []);
        $this->suburbAreas = implode(', ', $profile['shipping_zones']['suburb'] ?? []);
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

        $settings->save($this->settingsPayload());

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
            'invoice.hotline' => ['nullable', 'string', 'max:60'],
            'invoice.support_hotline' => ['nullable', 'string', 'max:60'],
            'invoice.facebook_url' => ['nullable', 'string', 'max:255'],
            'invoice.facebook_label' => ['nullable', 'string', 'max:120'],
            'invoice.whatsapp' => ['nullable', 'string', 'max:60'],
            'invoice.website' => ['nullable', 'string', 'max:255'],
            'invoice.thank_you' => ['nullable', 'string', 'max:255'],
            'invoice.show_images' => ['boolean'],
            'invoice.show_weight' => ['boolean'],
            'invoice.show_barcode' => ['boolean'],
            'invoice.show_slip' => ['boolean'],
            'insideAreas' => ['nullable', 'string', 'max:1000'],
            'outsideAreas' => ['nullable', 'string', 'max:1000'],
            'suburbAreas' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function settingsPayload(): array
    {
        return [
            'name' => $this->name,
            'logo' => $this->logo,
            'dark_logo' => $this->darkLogo,
            'address' => $this->address,
            'phone' => $this->phone,
            'email' => $this->email,
            'currency' => $this->currency,
            'timezone' => $this->timezone,
            'date_format' => $this->dateFormat,
            'shipping_zones' => [
                'inside' => $this->splitAreas($this->insideAreas),
                'outside' => $this->splitAreas($this->outsideAreas),
                'suburb' => $this->splitAreas($this->suburbAreas),
            ],
        ];
    }

    protected function splitAreas(string $areas): array
    {
        return collect(explode(',', $areas))
            ->map(fn (string $area): string => trim($area))
            ->filter()
            ->values()
            ->all();
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
        $settings = app(CompanySettingsService::class);
        $settings->save($this->settingsPayload());
        $settings->saveInvoice($this->invoice);
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
