<?php

namespace App\Filament\Resources\StorefrontSettings;

use App\Filament\Resources\StorefrontPages\StorefrontPageResource;
use App\Filament\Resources\StorefrontSettings\Pages\CreateStorefrontSetting;
use App\Filament\Resources\StorefrontSettings\Pages\EditStorefrontSetting;
use App\Filament\Resources\StorefrontSettings\Pages\ListStorefrontSettings;
use App\Models\Company;
use App\Models\Product;
use App\Models\StorefrontPage;
use App\Models\StorefrontSetting;
use App\Services\WooCommerceImportService;
use Filament\Actions\Action;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Illuminate\Validation\Rule;
use UnitEnum;

class StorefrontSettingResource extends Resource
{
    protected static ?string $model = StorefrontSetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static string|UnitEnum|null $navigationGroup = 'Storefront';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'company.name';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Storefront Publishing')
                ->columnSpanFull()
                ->description('Connect a company to its public storefront and control whether it is visible.')
                ->schema([
                    Select::make('company_id')
                        ->relationship('company', 'name')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->unique(table: 'storefront_settings', column: 'company_id', ignoreRecord: true)
                        ->live()
                        ->afterStateUpdated(function (Set $set, ?int $state): void {
                            $company = $state ? Company::withoutGlobalScopes()->find($state) : null;

                            $set('company_domain', $company?->domain);
                            $set('company_domain_verified', (bool) $company?->domain_verified);
                        }),
                    Toggle::make('is_published')
                        ->label('Published')
                        ->default(false)
                        ->helperText('Unpublished storefronts return a 404 on public domain routes.'),
                    ColorPicker::make('theme_color')
                        ->label('Theme color')
                        ->default('#0F766E')
                        ->required(),
                    TextInput::make('whatsapp_number')
                        ->tel()
                        ->maxLength(40)
                        ->helperText('Optional. Used for quick customer contact buttons.'),
                    TextInput::make('phone_number')
                        ->label('Call support number')
                        ->tel()
                        ->maxLength(40)
                        ->helperText('Optional. Shows a "Call" button next to WhatsApp for customers who prefer to call.'),
                    Select::make('theme_mode')
                        ->label('Default color mode')
                        ->options(StorefrontSetting::THEME_MODES)
                        ->default('system')
                        ->required()
                        ->helperText('Used for a visitor\'s first visit only; their own light/dark toggle choice is remembered after that.'),
                ])
                ->columns(2)
                ->collapsible(),

            Section::make('Homepage Content')
                ->columnSpanFull()
                ->description('Override the default homepage hero copy. Leave blank to use the automatic default.')
                ->schema([
                    TextInput::make('hero_heading')
                        ->label('Hero heading')
                        ->maxLength(120)
                        ->placeholder('Shop the latest from {company name}.')
                        ->columnSpanFull(),
                    Textarea::make('hero_subheading')
                        ->label('Hero subheading')
                        ->rows(2)
                        ->maxLength(240)
                        ->placeholder('Browse current products, order directly, and track purchases from one clean storefront.')
                        ->columnSpanFull(),
                    TextInput::make('hero_cta_label')
                        ->label('Hero call-to-action label')
                        ->maxLength(40)
                        ->placeholder('Start shopping'),
                ])
                ->columns(2)
                ->collapsible(),

            Section::make('Trust Strip')
                ->columnSpanFull()
                ->description('Short reassurance lines shown as an icon row on the homepage. Leave a field blank to hide that item.')
                ->schema([
                    TextInput::make('trust_strip_delivery')
                        ->label('Delivery message')
                        ->maxLength(80)
                        ->placeholder('Fast delivery nationwide'),
                    TextInput::make('trust_strip_return')
                        ->label('Return/warranty message')
                        ->maxLength(80)
                        ->placeholder('Easy returns within 7 days'),
                    TextInput::make('trust_strip_payment')
                        ->label('Payment message')
                        ->maxLength(80)
                        ->placeholder('Cash on delivery available'),
                ])
                ->columns(3)
                ->collapsible(),

            Section::make('Offer Countdown')
                ->columnSpanFull()
                ->description('An optional sitewide flash-sale banner with a countdown, shown on the homepage until it ends. Leave the title blank to hide it.')
                ->schema([
                    TextInput::make('offer_title')
                        ->label('Offer title')
                        ->maxLength(120)
                        ->placeholder('Flash Sale'),
                    TextInput::make('offer_discount_percent')
                        ->label('Discount %')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(100),
                    DateTimePicker::make('offer_ends_at')
                        ->label('Ends at')
                        ->helperText('The banner disappears automatically once this time passes.'),
                ])
                ->columns(3)
                ->collapsible(),

            Section::make('Domain and Launch Readiness')
                ->columnSpanFull()
                ->description('These fields power the Storefront Settings list readiness columns.')
                ->schema([
                    TextInput::make('company_domain')
                        ->label('Storefront Domain')
                        ->maxLength(255)
                        ->dehydrateStateUsing(fn (?string $state): ?string => Company::normalizeDomain($state))
                        ->rule(function (Get $get, ?StorefrontSetting $record) {
                            $companyId = $get('company_id') ?: $record?->company_id;

                            return Rule::unique('companies', 'domain')
                                ->ignore($companyId)
                                ->whereNotNull('domain');
                        })
                        ->helperText('Example: zamzamgadgetbd.com. Do not include https:// or paths.')
                        ->afterStateHydrated(function (TextInput $component, ?StorefrontSetting $record): void {
                            $component->state($record?->company?->domain);
                        }),
                    Toggle::make('company_domain_verified')
                        ->label('Domain verified')
                        ->helperText('Turn on only after DNS/server routing is verified.')
                        ->afterStateHydrated(function (Toggle $component, ?StorefrontSetting $record): void {
                            $component->state((bool) $record?->company?->domain_verified);
                        }),
                    TextInput::make('launch_readiness_display')
                        ->label('Launch Readiness')
                        ->disabled()
                        ->dehydrated(false)
                        ->afterStateHydrated(function (TextInput $component, ?StorefrontSetting $record): void {
                            $component->state($record ? self::readinessSummary($record) : 'Save first to calculate');
                        }),
                    Textarea::make('missing_setup_display')
                        ->label('Missing Setup')
                        ->rows(3)
                        ->disabled()
                        ->dehydrated(false)
                        ->afterStateHydrated(function (Textarea $component, ?StorefrontSetting $record): void {
                            $component->state($record ? self::missingSetup($record) : 'Save first to calculate');
                        }),
                    TextInput::make('visible_products_display')
                        ->label('Visible Products')
                        ->disabled()
                        ->dehydrated(false)
                        ->afterStateHydrated(function (TextInput $component, ?StorefrontSetting $record): void {
                            $component->state($record ? (string) self::productCount($record) : '0');
                        }),
                    TextInput::make('published_pages_display')
                        ->label('Published Pages')
                        ->disabled()
                        ->dehydrated(false)
                        ->afterStateHydrated(function (TextInput $component, ?StorefrontSetting $record): void {
                            $component->state($record ? (string) self::pageCount($record) : '0');
                        }),
                ])
                ->columns(2)
                ->collapsible(),

            Section::make('Branding')
                ->columnSpanFull()
                ->schema([
                    FileUpload::make('logo')
                        ->label('Logo (light mode)')
                        ->helperText('Full horizontal logo, transparent PNG/SVG preferred. Recommended: 400x100px (4:1 ratio); shown at 40px height. When uploaded, it replaces the site name text in the storefront header.')
                        ->image()
                        ->maxSize(1024)
                        ->disk('public')
                        ->directory('storefront/logos')
                        ->imageEditor()
                        ->downloadable()
                        ->openable(),
                    FileUpload::make('logo_dark')
                        ->label('Logo (dark mode)')
                        ->helperText('Optional. Light-colored logo version for dark mode visitors. Same recommended size: 400x100px. If empty, the light-mode logo is used in both modes.')
                        ->image()
                        ->maxSize(1024)
                        ->disk('public')
                        ->directory('storefront/logos')
                        ->imageEditor()
                        ->downloadable()
                        ->openable(),
                    self::bannerRepeater(
                        name: 'banner_images',
                        label: 'Banner images (desktop)',
                        description: 'Shown on desktop/tablet when no hero slides are configured. Multiple banners rotate automatically. Recommended: 1600x680px (~21:9 wide aspect ratio). Supports any image format — JPG, PNG, WEBP, GIF, SVG, BMP, AVIF, etc.',
                    ),
                    self::bannerRepeater(
                        name: 'banner_images_mobile',
                        label: 'Banner images (mobile)',
                        description: 'Optional. Shown on phones instead of the desktop banners above. Recommended: 900x1200px (~3:4 vertical aspect ratio). Falls back to the desktop banners if left empty. Supports any image format.',
                    ),
                ])
                ->columns(2)
                ->collapsible(),

            Section::make('Checkout & Delivery')
                ->columnSpanFull()
                ->description('Controls shown on the one-page storefront checkout.')
                ->schema([
                    Toggle::make('cod_enabled')
                        ->label('Enable Cash on Delivery')
                        ->default(true),
                    TextInput::make('delivery_charge_inside')
                        ->label('Delivery charge (inside Dhaka)')
                        ->numeric()
                        ->prefix('BDT')
                        ->placeholder('60'),
                    TextInput::make('delivery_charge_outside')
                        ->label('Delivery charge (outside Dhaka)')
                        ->numeric()
                        ->prefix('BDT')
                        ->placeholder('120'),
                    TextInput::make('manual_bkash_number')
                        ->label('bKash Send Money number')
                        ->maxLength(20)
                        ->placeholder('01XXXXXXXXX'),
                    Textarea::make('manual_bkash_instructions')
                        ->label('bKash instructions')
                        ->rows(2)
                        ->maxLength(500)
                        ->placeholder('Send Money to this number, then enter the Transaction ID below.')
                        ->columnSpanFull(),
                    TextInput::make('manual_nagad_number')
                        ->label('Nagad Send Money number')
                        ->maxLength(20)
                        ->placeholder('01XXXXXXXXX'),
                    Textarea::make('manual_nagad_instructions')
                        ->label('Nagad instructions')
                        ->rows(2)
                        ->maxLength(500)
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->collapsible(),

            Section::make('Online Payments (ZiniPay)')
                ->columnSpanFull()
                ->description('Used to collect advance payments for pre-order items. COD stays the default for in-stock items.')
                ->schema([
                    Toggle::make('online_payment_enabled')
                        ->label('Enable online payments')
                        ->default(false)
                        ->helperText('Turn on only after the ZiniPay API key below is set.'),
                    TextInput::make('payment_credentials.zinipay_api_key')
                        ->label('ZiniPay API key')
                        ->password()
                        ->revealable()
                        ->maxLength(255),
                    TextInput::make('payment_credentials.zinipay_base_url')
                        ->label('ZiniPay base URL')
                        ->url()
                        ->maxLength(255)
                        ->placeholder(\App\Services\ZiniPayClient::DEFAULT_BASE_URL)
                        ->helperText('Leave empty for the default. Change only if ZiniPay gives you a different API host.'),
                ])
                ->columns(2)
                ->collapsible()
                ->collapsed(),

            Section::make('Abandoned Cart Reminders')
                ->columnSpanFull()
                ->description('Automatic SMS/WhatsApp reminders for carts left behind after a checkout attempt. Runs hourly via the scheduler.')
                ->schema([
                    Toggle::make('abandoned_cart_reminders_enabled')
                        ->label('Enable reminders')
                        ->default(false),
                    TextInput::make('abandoned_cart_delay_hours')
                        ->label('Remind after (hours)')
                        ->integer()
                        ->default(6)
                        ->minValue(1)
                        ->maxValue(168),
                    TextInput::make('notification_credentials.sms_api_url')
                        ->label('SMS gateway URL template')
                        ->maxLength(500)
                        ->placeholder('http://bulksmsbd.net/api/smsapi?api_key={api_key}&type=text&number={phone}&senderid={sender_id}&message={message}')
                        ->helperText('Use {api_key}, {sender_id}, {phone}, {message} placeholders. Works with any GET-based SMS gateway.')
                        ->columnSpanFull(),
                    TextInput::make('notification_credentials.sms_api_key')
                        ->label('SMS API key')
                        ->password()
                        ->revealable()
                        ->maxLength(255),
                    TextInput::make('notification_credentials.sms_sender_id')
                        ->label('SMS sender ID')
                        ->maxLength(100),
                    TextInput::make('notification_credentials.whatsapp_token')
                        ->label('WhatsApp Cloud API token')
                        ->password()
                        ->revealable()
                        ->maxLength(500),
                    TextInput::make('notification_credentials.whatsapp_phone_number_id')
                        ->label('WhatsApp phone number ID')
                        ->maxLength(100),
                    TextInput::make('notification_credentials.whatsapp_template_name')
                        ->label('WhatsApp template name')
                        ->maxLength(100)
                        ->helperText('Meta-approved template with two body variables: customer name and store name.'),
                    TextInput::make('notification_credentials.whatsapp_template_language')
                        ->label('WhatsApp template language')
                        ->maxLength(10)
                        ->placeholder('bn'),
                ])
                ->columns(2)
                ->collapsible()
                ->collapsed(),

            Section::make('WooCommerce Import')
                ->columnSpanFull()
                ->description('Optional. Save these credentials, then use the "Sync WooCommerce" button on this row in the list page to pull published products from the old WooCommerce site.')
                ->schema([
                    TextInput::make('woocommerce_base_url')
                        ->label('WooCommerce site URL')
                        ->url()
                        ->maxLength(255)
                        ->placeholder('https://zamzamgadgetbd.com')
                        ->helperText('Root URL of the WooCommerce site. Do not include /wp-json.'),
                    TextInput::make('woocommerce_credentials.consumer_key')
                        ->label('Consumer key')
                        ->password()
                        ->revealable()
                        ->maxLength(255),
                    TextInput::make('woocommerce_credentials.consumer_secret')
                        ->label('Consumer secret')
                        ->password()
                        ->revealable()
                        ->maxLength(255),
                ])
                ->columns(2)
                ->collapsible()
                ->collapsed(),

            Section::make('SEO')
                ->columnSpanFull()
                ->schema([
                    TextInput::make('meta_title')
                        ->maxLength(70),
                    Textarea::make('meta_description')
                        ->rows(3)
                        ->maxLength(160)
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('logo')
                    ->disk('public')
                    ->height(36)
                    ->square()
                    ->toggleable(),
                TextColumn::make('company.name')
                    ->label('Company')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('company.domain')
                    ->label('Domain')
                    ->searchable()
                    ->placeholder('-'),
                IconColumn::make('company.domain_verified')
                    ->label('Domain Verified')
                    ->boolean()
                    ->sortable()
                    ->toggleable(),
                IconColumn::make('is_published')
                    ->label('Published')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('launch_readiness')
                    ->label('Launch Readiness')
                    ->state(fn (StorefrontSetting $record): string => self::readinessSummary($record))
                    ->badge()
                    ->color(fn (StorefrontSetting $record): string => self::readinessColor($record)),
                TextColumn::make('storefront_products_count')
                    ->label('Products')
                    ->state(fn (StorefrontSetting $record): int => self::productCount($record))
                    ->badge()
                    ->toggleable(),
                TextColumn::make('storefront_pages_count')
                    ->label('Pages')
                    ->state(fn (StorefrontSetting $record): int => self::pageCount($record))
                    ->badge()
                    ->toggleable(),
                TextColumn::make('missing_setup')
                    ->label('Missing Setup')
                    ->state(fn (StorefrontSetting $record): string => self::missingSetup($record))
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('theme_color')
                    ->badge(),
                TextColumn::make('whatsapp_number')
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->url(fn (StorefrontSetting $record): string => self::previewUrl($record))
                    ->openUrlInNewTab(),
                Action::make('openSite')
                    ->label('Open Site')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (StorefrontSetting $record): string => self::publicUrl($record))
                    ->openUrlInNewTab()
                    ->visible(fn (StorefrontSetting $record): bool => filled($record->company?->domain)),
                Action::make('managePages')
                    ->label('Pages')
                    ->icon('heroicon-o-document-text')
                    ->url(fn (): string => StorefrontPageResource::getUrl('index')),
                self::syncWooCommerceAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function readinessChecks(StorefrontSetting $record): array
    {
        return [
            'Published' => $record->is_published,
            'Domain added' => filled($record->company?->domain),
            'Domain verified' => (bool) $record->company?->domain_verified,
            'Logo uploaded' => filled($record->logo),
            'Banner uploaded' => collect($record->banner_images ?? [])->filter()->isNotEmpty(),
            'SEO completed' => filled($record->meta_title) && filled($record->meta_description),
            'WhatsApp added' => filled($record->whatsapp_number),
            'Content pages' => self::pageCount($record) > 0,
            'Products visible' => self::productCount($record) > 0,
        ];
    }

    public static function readinessSummary(StorefrontSetting $record): string
    {
        $checks = self::readinessChecks($record);
        $passed = collect($checks)->filter()->count();

        return "{$passed}/".count($checks).' ready';
    }

    public static function readinessColor(StorefrontSetting $record): string
    {
        $checks = self::readinessChecks($record);
        $passed = collect($checks)->filter()->count();
        $total = count($checks);

        return match (true) {
            $passed === $total => 'success',
            $passed >= 6 => 'warning',
            default => 'danger',
        };
    }

    public static function missingSetup(StorefrontSetting $record): string
    {
        $missing = collect(self::readinessChecks($record))
            ->reject()
            ->keys()
            ->values();

        return $missing->isEmpty() ? 'Ready to launch' : $missing->implode(', ');
    }

    public static function productCount(StorefrontSetting $record): int
    {
        return Product::withoutGlobalScopes()
            ->where('company_id', $record->company_id)
            ->where('is_active', true)
            ->where('status', Product::STATUS_AVAILABLE)
            ->count();
    }

    public static function pageCount(StorefrontSetting $record): int
    {
        return StorefrontPage::withoutGlobalScopes()
            ->where('company_id', $record->company_id)
            ->where('is_published', true)
            ->count();
    }

    public static function previewUrl(StorefrontSetting $record): string
    {
        return route('storefront.preview.show', (string) $record->company?->slug);
    }

    public static function publicUrl(StorefrontSetting $record): string
    {
        return 'https://'.$record->company->domain;
    }

    public static function hasWooCommerceCredentials(StorefrontSetting $record): bool
    {
        return filled($record->woocommerce_base_url)
            && filled(data_get($record->woocommerce_credentials, 'consumer_key'))
            && filled(data_get($record->woocommerce_credentials, 'consumer_secret'));
    }

    public static function bannerRepeater(string $name, string $label, string $description): Repeater
    {
        return Repeater::make($name)
            ->label($label)
            ->helperText($description)
            ->reorderable()
            ->collapsible()
            ->addActionLabel('Add banner')
            ->itemLabel(fn (array $state): string => filled($state['product_id'] ?? null)
                ? (Product::withoutGlobalScopes()->find($state['product_id'])?->name ?? 'Banner')
                : 'Banner')
            ->schema([
                FileUpload::make('image')
                    ->label('Image')
                    ->required()
                    ->image()
                    ->disk('public')
                    ->directory('storefront/banners')
                    ->imageEditor()
                    ->downloadable()
                    ->openable(),
                Select::make('product_id')
                    ->label('Link to product (optional)')
                    ->helperText('Clicking this banner sends visitors straight to the tagged product\'s page. Leave blank for no link.')
                    ->searchable()
                    ->options(function (Get $get, ?StorefrontSetting $record) {
                        $companyId = $record?->company_id ?? $get('../../company_id');

                        return $companyId
                            ? Product::withoutGlobalScopes()->where('company_id', $companyId)->orderBy('name')->limit(100)->pluck('name', 'id')
                            : [];
                    })
                    ->getSearchResultsUsing(function (string $search, Get $get, ?StorefrontSetting $record) {
                        $companyId = $record?->company_id ?? $get('../../company_id');

                        return $companyId
                            ? Product::withoutGlobalScopes()->where('company_id', $companyId)->where('name', 'like', "%{$search}%")->limit(50)->pluck('name', 'id')
                            : [];
                    })
                    ->getOptionLabelUsing(fn ($value): ?string => Product::withoutGlobalScopes()->find($value)?->name),
            ])
            ->columnSpanFull();
    }

    public static function syncWooCommerceAction(): Action
    {
        return Action::make('syncWooCommerce')
            ->label('Sync WooCommerce')
            ->icon('heroicon-o-arrow-path')
            ->color('gray')
            ->visible(fn (StorefrontSetting $record): bool => self::hasWooCommerceCredentials($record))
            ->requiresConfirmation()
            ->modalDescription('Pulls published products from the WooCommerce site into this company\'s catalog. Products are matched by SKU/slug and updated; nothing is deleted.')
            ->schema([
                Toggle::make('download_images')
                    ->label('Download product images')
                    ->default(true),
            ])
            ->action(function (StorefrontSetting $record, array $data): void {
                try {
                    $result = app(WooCommerceImportService::class)->importProducts(
                        $record->company,
                        downloadImages: (bool) ($data['download_images'] ?? true),
                    );

                    Notification::make()
                        ->title('WooCommerce sync complete')
                        ->body("Created: {$result['created']}, updated: {$result['updated']}, skipped: {$result['skipped']}.")
                        ->success()
                        ->send();
                } catch (\RuntimeException $exception) {
                    Notification::make()
                        ->title('WooCommerce sync failed')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    public static function canViewAny(): bool
    {
        return SchemaFacade::hasTable('storefront_settings') && (Auth::user()?->canManageSettings() ?? false);
    }

    public static function canCreate(): bool
    {
        return SchemaFacade::hasTable('storefront_settings') && (Auth::user()?->canManageSettings() ?? false);
    }

    public static function canEdit($record): bool
    {
        return SchemaFacade::hasTable('storefront_settings') && (Auth::user()?->canManageSettings() ?? false);
    }

    public static function canDelete($record): bool
    {
        return SchemaFacade::hasTable('storefront_settings') && (Auth::user()?->isSuperAdmin() ?? false);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStorefrontSettings::route('/'),
            'create' => CreateStorefrontSetting::route('/create'),
            'edit' => EditStorefrontSetting::route('/{record}/edit'),
        ];
    }
}
