<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Storefront;
use App\Filament\Resources\StorefrontMetaEvents\StorefrontMetaEventResource;
use App\Models\StorefrontMetaEvent;
use App\Models\StorefrontSetting;
use App\Services\CompanyContext;
use App\Services\StorefrontMetaTrackingService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;

class MetaCapiSettings extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static ?string $cluster = Storefront::class;

    protected static ?int $navigationSort = 9;

    protected static ?string $navigationLabel = 'Meta CAPI';

    protected static ?string $title = 'Meta Pixel & Conversions API';

    protected string $view = 'filament.pages.meta-capi-settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    #[Locked]
    public ?int $companyId = null;

    #[Locked]
    public ?int $settingId = null;

    #[Locked]
    public ?string $companyName = null;

    #[Url(as: 'section', history: true)]
    public string $activeSection = 'overview';

    public function mount(): void
    {
        $context = app(CompanyContext::class);

        if ($context->isAllCompanies()) {
            $this->normalizeActiveSection();

            return;
        }

        $company = $context->company();
        abort_unless($company !== null, 404);

        $this->companyId = (int) $company->getKey();
        $this->companyName = $company->name;

        $setting = StorefrontSetting::withoutGlobalScopes()
            ->where('company_id', $this->companyId)
            ->first();

        $this->settingId = $setting?->getKey();
        $this->form->fill($setting?->only($this->metaFields()) ?? []);
        $this->normalizeActiveSection();
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();
        $context = app(CompanyContext::class);

        if (! SchemaFacade::hasTable('storefront_settings') || ! SchemaFacade::hasTable('storefront_meta_events')) {
            return false;
        }

        if (! $user || (! $user->canManageSettings() && ! $user->hasPermission('sales.view'))) {
            return false;
        }

        if ($context->isAllCompanies()) {
            return $user->isSuperAdmin();
        }

        $company = $context->company();

        return (bool) ($company && $user->canAccessCompany((int) $company->getKey()));
    }

    public function getSubheading(): ?string
    {
        if (! $this->hasSelectedCompany()) {
            return 'Select a company to configure tracking. The event log below can still show all companies when that view is authorized.';
        }

        return "Configure consented browser and server-side Meta measurement for {$this->companyName}.";
    }

    public function getHeader(): ?View
    {
        return view('filament.pages.partials.meta-capi-settings-header', [
            'activeSection' => $this->activeSection,
            'breadcrumbs' => filament()->hasBreadcrumbs() ? $this->getBreadcrumbs() : [],
            'headerActions' => $this->getCachedHeaderActions(),
            'headerActionsAlignment' => $this->getHeaderActionsAlignment(),
            'heading' => $this->getHeading(),
            'sectionItems' => $this->sectionNavigation(),
            'subheading' => $this->getSubheading(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('saveMetaSettings')
                ->label('Save settings')
                ->icon(Heroicon::OutlinedCheck)
                ->submit('save')
                ->formId('meta-capi-settings-form')
                ->keyBindings(['mod+s'])
                ->visible(fn (): bool => $this->hasSelectedCompany()
                    && $this->canManageMetaSettings()
                    && $this->activeSection !== 'event_log'),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->columns(1)
            ->components([
                Section::make('Overview')
                    ->id('meta-overview')
                    ->extraAttributes(['class' => 'zz-meta-anchor'])
                    ->visible(fn (): bool => $this->isSectionActive('overview'))
                    ->dehydratedWhenHidden()
                    ->icon('heroicon-o-signal')
                    ->description('Master control and a quick readiness summary for the selected storefront.')
                    ->schema([
                        Toggle::make('meta_tracking_enabled')
                            ->label('Enable Meta tracking')
                            ->default(false)
                            ->live()
                            ->columnSpanFull(),
                        Placeholder::make('browser_readiness')
                            ->label('Browser tracking')
                            ->content(fn (Get $get): string => $this->readinessLabel(
                                (bool) $get('meta_tracking_enabled') && (bool) $get('meta_browser_tracking_enabled'),
                                filled($get('meta_pixel_id')),
                            )),
                        Placeholder::make('capi_readiness')
                            ->label('Conversions API')
                            ->content(fn (Get $get): string => $this->readinessLabel(
                                (bool) $get('meta_tracking_enabled') && (bool) $get('meta_capi_enabled'),
                                filled($get('meta_pixel_id')) && filled($get('meta_tracking_credentials.access_token')),
                            )),
                        Placeholder::make('consent_readiness')
                            ->label('Consent gate')
                            ->content(fn (Get $get): string => (bool) $get('meta_consent_required')
                                ? 'Required before measurement'
                                : 'Disabled by administrator'),
                    ])
                    ->columns(3),

                Section::make('Connection & Pixels')
                    ->id('meta-connection')
                    ->extraAttributes(['class' => 'zz-meta-anchor'])
                    ->visible(fn (): bool => $this->isSectionActive('connection'))
                    ->dehydratedWhenHidden()
                    ->icon('heroicon-o-link')
                    ->description('Connect the primary Pixel/Dataset, optional additional destinations, domain verification, and CAPI credentials.')
                    ->schema([
                        TextInput::make('meta_pixel_id')
                            ->label('Primary Pixel / Dataset ID')
                            ->maxLength(32)
                            ->regex('/^\d{5,32}$/'),
                        Toggle::make('meta_capi_enabled')
                            ->label('Server-side events')
                            ->default(false)
                            ->live()
                            ->helperText('Provider failure never blocks checkout or order operations.'),
                        TextInput::make('meta_tracking_credentials.access_token')
                            ->label('Conversions API access token')
                            ->password()
                            ->revealable()
                            ->maxLength(1000)
                            ->helperText('Stored encrypted and kept separate from WhatsApp/Messenger channel tokens.')
                            ->visible(fn (Get $get): bool => (bool) $get('meta_capi_enabled')),
                        TextInput::make('meta_tracking_credentials.test_event_code')
                            ->label('Test event code')
                            ->maxLength(100)
                            ->helperText('Optional. Clear the Events Manager test code before production traffic.')
                            ->visible(fn (Get $get): bool => (bool) $get('meta_capi_enabled')),
                        Repeater::make('meta_tracking_credentials.additional_pixels')
                            ->label('Additional Pixels / Datasets')
                            ->schema([
                                TextInput::make('pixel_id')
                                    ->label('Pixel / Dataset ID')
                                    ->required()
                                    ->regex('/^\d{5,32}$/')
                                    ->maxLength(32),
                                TextInput::make('access_token')
                                    ->label('CAPI access token')
                                    ->password()
                                    ->revealable()
                                    ->maxLength(1000),
                                TextInput::make('test_event_code')
                                    ->label('Test event code')
                                    ->maxLength(100),
                            ])
                            ->columns(3)
                            ->addActionLabel('Add Pixel / Dataset')
                            ->helperText('The complete list is encrypted with the primary CAPI credentials. A token is optional for browser-only delivery.')
                            ->columnSpanFull(),
                        Repeater::make('meta_domain_verification_tags')
                            ->label('Domain verification values')
                            ->schema([
                                TextInput::make('content')
                                    ->label('facebook-domain-verification content')
                                    ->required()
                                    ->regex('/^[A-Za-z0-9_-]{8,255}$/')
                                    ->maxLength(255),
                            ])
                            ->addActionLabel('Add verification value')
                            ->helperText('Enter only the content value from Meta, not the full HTML meta tag.')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Consent & Advanced Matching')
                    ->id('meta-consent')
                    ->extraAttributes(['class' => 'zz-meta-anchor'])
                    ->visible(fn (): bool => $this->isSectionActive('consent'))
                    ->dehydratedWhenHidden()
                    ->icon('heroicon-o-shield-check')
                    ->description('Control the storefront consent gate and privacy-minimized authenticated-customer matching.')
                    ->schema([
                        Toggle::make('meta_consent_required')
                            ->label('Require customer consent')
                            ->default(false)
                            ->live()
                            ->helperText('Off by default: Pixel and server events fire for every visitor immediately. Turn on only if local regulation requires an accept/decline choice before measurement — while on, Pixel and server events stay off until each customer accepts.'),
                        Toggle::make('meta_advanced_matching_enabled')
                            ->label('Authenticated-customer advanced matching')
                            ->default(false)
                            ->helperText('Initializes each browser Pixel with SHA-256 hashed account identifiers; raw identifiers are never embedded.')
                            ->visible(fn (Get $get): bool => (bool) $get('meta_browser_tracking_enabled')),
                        Textarea::make('meta_consent_message')
                            ->label('Consent message')
                            ->rows(3)
                            ->maxLength(500)
                            ->placeholder('We use Meta measurement cookies to understand storefront activity and improve advertising.')
                            ->columnSpanFull()
                            ->visible(fn (Get $get): bool => (bool) $get('meta_consent_required')),
                    ])
                    ->columns(2),

                Section::make('Browser Events')
                    ->id('meta-browser-events')
                    ->extraAttributes(['class' => 'zz-meta-anchor'])
                    ->visible(fn (): bool => $this->isSectionActive('browser_events'))
                    ->dehydratedWhenHidden()
                    ->icon('heroicon-o-cursor-arrow-rays')
                    ->description('Choose standard storefront funnel events and optional selector-based behavioural events.')
                    ->schema([
                        Toggle::make('meta_browser_tracking_enabled')
                            ->label('Browser Pixel events')
                            ->default(true)
                            ->live()
                            ->helperText('Tracks only the selected storefront events after consent, when consent is required.'),
                        CheckboxList::make('meta_browser_events')
                            ->label('Browser events to send')
                            ->options(StorefrontMetaTrackingService::BROWSER_EVENTS)
                            ->default(StorefrontMetaTrackingService::DEFAULT_BROWSER_EVENTS)
                            ->columns(2)
                            ->columnSpanFull()
                            ->visible(fn (Get $get): bool => (bool) $get('meta_browser_tracking_enabled')),
                        Toggle::make('meta_custom_events_enabled')
                            ->label('Custom behavioural events')
                            ->default(false)
                            ->live()
                            ->helperText('Use stable CSS selectors that exist on the storefront.')
                            ->visible(fn (Get $get): bool => (bool) $get('meta_browser_tracking_enabled')),
                        Repeater::make('meta_custom_events')
                            ->label('Link, click, visibility, and time events')
                            ->schema([
                                Select::make('type')
                                    ->options(StorefrontMetaTrackingService::CUSTOM_EVENT_TYPES)
                                    ->required()
                                    ->live(),
                                TextInput::make('event_name')
                                    ->label('Meta custom event name')
                                    ->required()
                                    ->regex('/^[A-Za-z][A-Za-z0-9_]{0,49}$/')
                                    ->maxLength(50),
                                TextInput::make('selector')
                                    ->label('CSS selector')
                                    ->required(fn (Get $get): bool => in_array($get('type'), ['link', 'click', 'scroll'], true))
                                    ->maxLength(255)
                                    ->placeholder('#whatsapp-button')
                                    ->visible(fn (Get $get): bool => in_array($get('type'), ['link', 'click', 'scroll'], true)),
                                TextInput::make('seconds')
                                    ->label('Seconds on page')
                                    ->numeric()
                                    ->integer()
                                    ->minValue(1)
                                    ->maxValue(3600)
                                    ->default(10)
                                    ->visible(fn (Get $get): bool => $get('type') === 'time'),
                            ])
                            ->columns(2)
                            ->addActionLabel('Add custom event')
                            ->reorderable()
                            ->columnSpanFull()
                            ->visible(fn (Get $get): bool => (bool) $get('meta_browser_tracking_enabled')
                                && (bool) $get('meta_custom_events_enabled')),
                    ]),

                Section::make('Purchase Delivery')
                    ->id('meta-purchase')
                    ->extraAttributes(['class' => 'zz-meta-anchor'])
                    ->visible(fn (): bool => $this->isSectionActive('purchase'))
                    ->dehydratedWhenHidden()
                    ->icon('heroicon-o-shopping-cart')
                    ->description('Choose when the deduplicated Purchase event is eligible for server delivery.')
                    ->schema([
                        Select::make('meta_purchase_timing')
                            ->label('Purchase event timing')
                            ->options(StorefrontMetaTrackingService::PURCHASE_TIMINGS)
                            ->default('immediate')
                            ->required()
                            ->live()
                            ->helperText('Delayed Purchase retains encrypted attribution only until successful server delivery.'),
                        TextInput::make('meta_purchase_success_ratio_threshold')
                            ->label('Trusted courier success ratio')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->default(70)
                            ->visible(fn (Get $get): bool => $get('meta_purchase_timing') === 'risk_aware'),
                    ])
                    ->columns(2),

                Section::make('Order Status Events')
                    ->id('meta-status-events')
                    ->extraAttributes(['class' => 'zz-meta-anchor'])
                    ->visible(fn (): bool => $this->isSectionActive('status_events'))
                    ->dehydratedWhenHidden()
                    ->icon('heroicon-o-arrows-right-left')
                    ->description('Send selected committed order lifecycle changes as privacy-minimized custom server events.')
                    ->schema([
                        Toggle::make('meta_status_events_enabled')
                            ->label('Order status events')
                            ->default(false)
                            ->live()
                            ->helperText('Events use hashed customer identifiers and never inherit the admin browser session.'),
                        CheckboxList::make('meta_status_events')
                            ->label('Lifecycle events to send')
                            ->options(StorefrontMetaTrackingService::STATUS_EVENTS)
                            ->default(StorefrontMetaTrackingService::DEFAULT_STATUS_EVENTS)
                            ->columns(2)
                            ->columnSpanFull()
                            ->visible(fn (Get $get): bool => (bool) $get('meta_status_events_enabled')),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return StorefrontMetaEventResource::table($table)
            ->query($this->metaEventQuery());
    }

    public function save(): void
    {
        abort_unless($this->hasSelectedCompany() && $this->canManageMetaSettings(), 403);

        $setting = StorefrontSetting::withoutGlobalScopes()->updateOrCreate(
            ['company_id' => $this->companyId],
            $this->form->getState(),
        );

        $this->settingId = (int) $setting->getKey();
        $this->form->fill($setting->only($this->metaFields()));

        Notification::make()
            ->title('Meta CAPI settings saved')
            ->success()
            ->send();
    }

    public function hasSelectedCompany(): bool
    {
        return $this->companyId !== null;
    }

    public function canManageMetaSettings(): bool
    {
        return Auth::user()?->canManageSettings() ?? false;
    }

    public function canViewMetaEvents(): bool
    {
        return Auth::user()?->hasPermission('sales.view') ?? false;
    }

    public function selectSection(string $section): void
    {
        if (! in_array($section, $this->availableSectionKeys(), true)) {
            return;
        }

        $this->activeSection = $section;
    }

    public function isSectionActive(string $section): bool
    {
        return $this->activeSection === $section;
    }

    /** @return array<int, array{key: string, label: string, icon: string}> */
    public function sectionNavigation(): array
    {
        $items = [];

        if ($this->hasSelectedCompany() && $this->canManageMetaSettings()) {
            $items = [
                ['key' => 'overview', 'label' => 'Overview', 'icon' => 'heroicon-o-signal'],
                ['key' => 'connection', 'label' => 'Connection & Pixels', 'icon' => 'heroicon-o-link'],
                ['key' => 'consent', 'label' => 'Consent & Matching', 'icon' => 'heroicon-o-shield-check'],
                ['key' => 'browser_events', 'label' => 'Browser Events', 'icon' => 'heroicon-o-cursor-arrow-rays'],
                ['key' => 'purchase', 'label' => 'Purchase Delivery', 'icon' => 'heroicon-o-shopping-cart'],
                ['key' => 'status_events', 'label' => 'Status Events', 'icon' => 'heroicon-o-arrows-right-left'],
            ];
        }

        if ($this->canViewMetaEvents()) {
            $items[] = ['key' => 'event_log', 'label' => 'Event Log & Retries', 'icon' => 'heroicon-o-queue-list'];
        }

        return $items;
    }

    /** @return array<int, string> */
    protected function availableSectionKeys(): array
    {
        return array_column($this->sectionNavigation(), 'key');
    }

    protected function normalizeActiveSection(): void
    {
        $sections = $this->availableSectionKeys();

        if (! in_array($this->activeSection, $sections, true)) {
            $this->activeSection = $sections[0] ?? 'overview';
        }
    }

    protected function metaEventQuery(): Builder
    {
        $query = StorefrontMetaEvent::query();

        return $this->canViewMetaEvents() ? $query : $query->whereRaw('1 = 0');
    }

    protected function readinessLabel(bool $enabled, bool $complete): string
    {
        if (! $enabled) {
            return 'Disabled';
        }

        return $complete ? 'Ready' : 'Configuration incomplete';
    }

    /** @return array<int, string> */
    protected function metaFields(): array
    {
        return [
            'meta_tracking_enabled',
            'meta_consent_required',
            'meta_consent_message',
            'meta_browser_tracking_enabled',
            'meta_advanced_matching_enabled',
            'meta_browser_events',
            'meta_custom_events_enabled',
            'meta_custom_events',
            'meta_capi_enabled',
            'meta_purchase_timing',
            'meta_purchase_success_ratio_threshold',
            'meta_status_events_enabled',
            'meta_status_events',
            'meta_pixel_id',
            'meta_domain_verification_tags',
            'meta_tracking_credentials',
        ];
    }
}
