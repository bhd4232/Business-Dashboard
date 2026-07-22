<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\CompanyManagement;
use App\Filament\Concerns\OptimizesUploadedImages;
use App\Models\Company;
use App\Services\CompanyContext;
use App\Services\CompanySettingsService;
use App\Support\CompanyMedia;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;

class CompanySettings extends Page
{
    use OptimizesUploadedImages;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $cluster = CompanyManagement::class;

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Company Settings';

    protected static ?string $navigationLabel = 'Company Settings';

    protected string $view = 'filament.pages.company-settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    #[Locked]
    public ?int $companyId = null;

    public function mount(CompanySettingsService $settings): void
    {
        $company = $this->selectedCompany();
        $this->companyId = (int) $company->getKey();
        $profile = $settings->profile($company);

        $this->form->fill([
            'name' => $profile['name'],
            'logo' => $profile['logo'],
            'dark_logo' => $profile['dark_logo'],
            'address' => $profile['address'],
            'phone' => $profile['phone'],
            'email' => $profile['email'],
            'currency' => $profile['currency'],
            'timezone' => $profile['timezone'],
            'date_format' => $profile['date_format'],
            'invoice_prefix' => $profile['invoice_prefix'],
            'invoice' => $settings->invoice($company),
            'shipping' => [
                'inside' => implode(', ', $profile['shipping_zones']['inside'] ?? []),
                'outside' => implode(', ', $profile['shipping_zones']['outside'] ?? []),
                'suburb' => implode(', ', $profile['shipping_zones']['suburb'] ?? []),
            ],
        ]);
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();
        $context = app(CompanyContext::class);
        $company = $context->company();

        return (bool) ($user?->canManageSettings()
            && $company
            && $user->canAccessCompany((int) $company->getKey()));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Business Profile')
                    ->description('Identity and regional defaults for the currently selected company.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Company name')
                            ->required()
                            ->maxLength(120),
                        TextInput::make('email')
                            ->email()
                            ->maxLength(120),
                        TextInput::make('phone')
                            ->tel()
                            ->maxLength(60),
                        TextInput::make('currency')
                            ->required()
                            ->maxLength(12),
                        Select::make('timezone')
                            ->options($this->timezoneOptions())
                            ->required()
                            ->searchable(),
                        Select::make('date_format')
                            ->label('Date format')
                            ->options($this->dateFormatOptions())
                            ->required(),
                        Textarea::make('address')
                            ->rows(3)
                            ->maxLength(500)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Branding')
                    ->description('Company-specific logos used in the ERP, storefront, invoices, and PDF exports.')
                    ->schema([
                        $this->logoUpload('logo', 'Light logo'),
                        $this->logoUpload('dark_logo', 'Dark logo'),
                    ])
                    ->columns(2),

                Section::make('Invoice Settings')
                    ->description('These values apply only to the selected company and are used for its invoice number, print view, PDF, and courier cut-slip.')
                    ->schema([
                        TextInput::make('invoice_prefix')
                            ->label('Invoice prefix')
                            ->helperText('Uppercase letters, numbers, and hyphens only. It must be unique across companies.')
                            ->required()
                            ->minLength(2)
                            ->maxLength(20)
                            ->rule('regex:/^[A-Za-z0-9-]+$/')
                            ->rule(fn () => Rule::unique('companies', 'invoice_prefix')->ignore($this->selectedCompany()->getKey()))
                            ->dehydrateStateUsing(fn (?string $state): string => Str::upper(trim((string) $state))),
                        TextInput::make('invoice.hotline')
                            ->label('Header hotline')
                            ->tel()
                            ->maxLength(60),
                        TextInput::make('invoice.support_hotline')
                            ->label('Footer hotline')
                            ->tel()
                            ->maxLength(60),
                        TextInput::make('invoice.facebook_url')
                            ->label('Facebook page URL')
                            ->url()
                            ->maxLength(255),
                        TextInput::make('invoice.facebook_label')
                            ->label('Facebook page label')
                            ->maxLength(120),
                        TextInput::make('invoice.whatsapp')
                            ->label('WhatsApp number')
                            ->tel()
                            ->maxLength(60),
                        TextInput::make('invoice.website')
                            ->label('Website')
                            ->maxLength(255),
                        TextInput::make('invoice.thank_you')
                            ->label('Thank-you message')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Toggle::make('invoice.show_images')
                            ->label('Show product images'),
                        Toggle::make('invoice.show_weight')
                            ->label('Show product weight'),
                        Toggle::make('invoice.show_barcode')
                            ->label('Show invoice barcode'),
                        Toggle::make('invoice.show_slip')
                            ->label('Show courier cut-slip'),
                    ])
                    ->columns(2),

                Section::make('Shipping Zones')
                    ->description('Comma-separated area names used to match company-specific delivery fees.')
                    ->schema([
                        Textarea::make('shipping.inside')
                            ->label('Inside areas')
                            ->rows(3)
                            ->maxLength(1000),
                        Textarea::make('shipping.outside')
                            ->label('Outside areas')
                            ->rows(3)
                            ->maxLength(1000),
                        Textarea::make('shipping.suburb')
                            ->label('Suburb areas')
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public function save(CompanySettingsService $settings): void
    {
        $company = $this->selectedCompany();
        $state = $this->form->getState();

        $settings->save([
            'name' => $state['name'],
            'logo' => $state['logo'] ?? null,
            'dark_logo' => $state['dark_logo'] ?? null,
            'address' => $state['address'] ?? null,
            'phone' => $state['phone'] ?? null,
            'email' => $state['email'] ?? null,
            'currency' => $state['currency'],
            'timezone' => $state['timezone'],
            'date_format' => $state['date_format'],
            'invoice_prefix' => $state['invoice_prefix'],
            'shipping_zones' => [
                'inside' => $this->splitAreas(data_get($state, 'shipping.inside')),
                'outside' => $this->splitAreas(data_get($state, 'shipping.outside')),
                'suburb' => $this->splitAreas(data_get($state, 'shipping.suburb')),
            ],
        ], $company);
        $settings->saveInvoice((array) ($state['invoice'] ?? []), $company);

        Notification::make()
            ->title('Company settings saved')
            ->body("Profile and invoice settings were saved for {$company->name}.")
            ->success()
            ->send();
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

    protected function logoUpload(string $name, string $label): FileUpload
    {
        return FileUpload::make($name)
            ->label($label)
            ->image()
            ->maxSize(2048)
            ->disk(fn (): string => CompanyMedia::publicDiskName())
            ->directory(fn (): string => CompanyMedia::publicDirectory('company', $this->selectedCompany()))
            ->fetchFileInformation(false)
            ->getUploadedFileUsing(CompanyMedia::publicFileMetadataCallback())
            ->getOpenableFileUrlUsing(CompanyMedia::publicFileUrlCallback())
            ->getDownloadableFileUrlUsing(CompanyMedia::publicFileUrlCallback())
            ->saveUploadedFileUsing(static::optimizeCompactImageUpload())
            ->imageEditor()
            ->openable()
            ->downloadable();
    }

    protected function selectedCompany(): Company
    {
        $contextCompany = app(CompanyContext::class)->company();

        abort_unless($contextCompany !== null, 404, 'Select a company before opening Company Settings.');

        if ($this->companyId === null) {
            $company = $contextCompany;
        } else {
            abort_unless(
                (int) $contextCompany->getKey() === $this->companyId,
                409,
                'The selected company changed. Reload Company Settings before saving.',
            );

            $company = Company::query()->findOrFail($this->companyId);
        }

        $user = Auth::user();
        abort_unless($user?->canManageSettings() && $user->canAccessCompany((int) $company->getKey()), 403);

        return $company;
    }

    /** @return list<string> */
    protected function splitAreas(mixed $areas): array
    {
        return collect(explode(',', (string) $areas))
            ->map(fn (string $area): string => trim($area))
            ->filter()
            ->values()
            ->all();
    }
}
