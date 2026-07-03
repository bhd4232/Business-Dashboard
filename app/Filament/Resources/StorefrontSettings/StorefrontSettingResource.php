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
use Filament\Actions\Action;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
        return $schema->components([
            Section::make('Storefront Publishing')
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
                    Select::make('theme_mode')
                        ->label('Default color mode')
                        ->options(StorefrontSetting::THEME_MODES)
                        ->default('system')
                        ->required()
                        ->helperText('Used for a visitor\'s first visit only; their own light/dark toggle choice is remembered after that.'),
                ])
                ->columns(2),

            Section::make('Homepage Content')
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
                ->columns(2),

            Section::make('Domain and Launch Readiness')
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
                ->columns(2),

            Section::make('Branding')
                ->schema([
                    FileUpload::make('logo')
                        ->image()
                        ->disk('public')
                        ->directory('storefront/logos')
                        ->imageEditor()
                        ->downloadable()
                        ->openable(),
                    FileUpload::make('banner_images')
                        ->label('Banner images')
                        ->image()
                        ->multiple()
                        ->reorderable()
                        ->disk('public')
                        ->directory('storefront/banners')
                        ->imageEditor()
                        ->downloadable()
                        ->openable(),
                ])
                ->columns(2),

            Section::make('SEO')
                ->schema([
                    TextInput::make('meta_title')
                        ->maxLength(70),
                    Textarea::make('meta_description')
                        ->rows(3)
                        ->maxLength(160)
                        ->columnSpanFull(),
                ])
                ->columns(2),
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
