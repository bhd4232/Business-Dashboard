<?php

namespace App\Filament\Resources\CourierProviders;

use App\Filament\Resources\CourierProviders\Pages\CreateCourierProvider;
use App\Filament\Resources\CourierProviders\Pages\EditCourierProvider;
use App\Filament\Resources\CourierProviders\Pages\ListCourierProviders;
use App\Models\Company;
use App\Models\CourierProvider;
use App\Services\CompanyContext;
use App\Services\SteadfastCourierClient;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Illuminate\Support\Str;
use UnitEnum;

class CourierProviderResource extends Resource
{
    protected static ?string $model = CourierProvider::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = 'Courier';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Delivery Partner')
                ->schema([
                    Select::make('company_id')
                        ->label('Company')
                        ->options(fn (): array => Company::query()
                            ->where('is_active', true)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable()
                        ->preload()
                        ->required(fn (): bool => app(CompanyContext::class)->isAllCompanies())
                        ->visible(fn (): bool => app(CompanyContext::class)->isAllCompanies())
                        ->helperText('Select the company that will own this courier provider.'),
                    Select::make('driver')
                        ->label('Select Delivery Partner')
                        ->options(CourierProvider::DRIVERS)
                        ->default(CourierProvider::DRIVER_MANUAL)
                        ->required()
                        ->native(false)
                        ->live()
                        ->afterStateUpdated(function (Set $set, ?string $state): void {
                            $label = CourierProvider::DRIVERS[$state ?? CourierProvider::DRIVER_MANUAL] ?? 'Custom';

                            $set('name', $label);
                            $set('slug', Str::slug($label));
                        }),
                    TextInput::make('name')
                        ->label('Partner Name')
                        ->default('Custom')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Set $set, ?string $state): void {
                            if (filled($state)) {
                                $set('slug', Str::slug($state));
                            }
                        }),
                    TextInput::make('settings.contact_person')
                        ->label('Contact Person')
                        ->maxLength(255),
                    TextInput::make('settings.phone')
                        ->label('Phone Number')
                        ->tel()
                        ->maxLength(30),
                    TextInput::make('settings.warehouse')
                        ->label('Warehouse')
                        ->placeholder('Select Warehouse')
                        ->maxLength(255),
                    TextInput::make('slug')
                        ->default('custom')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),
                    Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),
                ])
                ->columns(2),

            Section::make('Set Delivery Fees')
                ->schema([
                    Select::make('settings.delivery_fee_mode')
                        ->label('Delivery Type')
                        ->options([
                            'regular' => 'Regular Delivery',
                            'express' => 'Express Delivery',
                        ])
                        ->default('regular')
                        ->native(false),
                    TextInput::make('settings.delivery_fees.inside')
                        ->label('Inside')
                        ->prefix('BDT')
                        ->numeric()
                        ->default(0),
                    TextInput::make('settings.delivery_fees.outside')
                        ->label('Outside')
                        ->prefix('BDT')
                        ->numeric()
                        ->default(0),
                    TextInput::make('settings.delivery_fees.suburb')
                        ->label('Suburb')
                        ->prefix('BDT')
                        ->numeric()
                        ->default(0),
                ])
                ->columns(2)
                ->collapsible(),

            Section::make('Courier Delivery Cost')
                ->schema([
                    Select::make('settings.courier_cost_mode')
                        ->label('Delivery Type')
                        ->options([
                            'regular' => 'Regular Delivery',
                            'express' => 'Express Delivery',
                        ])
                        ->default('regular')
                        ->native(false),
                    TextInput::make('settings.courier_costs.inside')
                        ->label('Inside')
                        ->prefix('BDT')
                        ->numeric()
                        ->default(0),
                    TextInput::make('settings.courier_costs.outside')
                        ->label('Outside')
                        ->prefix('BDT')
                        ->numeric()
                        ->default(0),
                    TextInput::make('settings.courier_costs.suburb')
                        ->label('Suburb')
                        ->prefix('BDT')
                        ->numeric()
                        ->default(0),
                ])
                ->columns(2)
                ->collapsible(),

            Section::make('Courier Return Cost')
                ->schema([
                    TextInput::make('settings.return_costs.inside')
                        ->label('Inside')
                        ->prefix('BDT')
                        ->numeric()
                        ->default(0),
                    TextInput::make('settings.return_costs.outside')
                        ->label('Outside')
                        ->prefix('BDT')
                        ->numeric()
                        ->default(0),
                    TextInput::make('settings.return_costs.suburb')
                        ->label('Suburb')
                        ->prefix('BDT')
                        ->numeric()
                        ->default(0),
                    TextInput::make('settings.cod_charge_percent')
                        ->label('COD Charge (Percentage)')
                        ->suffix('%')
                        ->numeric()
                        ->default(0),
                ])
                ->columns(2)
                ->collapsible(),

            Section::make('API Integration')
                ->schema([
                    TextInput::make('settings.base_url')
                        ->label('Base URL')
                        ->url()
                        ->default(SteadfastCourierClient::DEFAULT_BASE_URL)
                        ->placeholder('https://portal.packzy.com/api/v1')
                        ->helperText('Steadfast default: https://portal.packzy.com/api/v1'),
                    TextInput::make('credentials.api_key')
                        ->label('API Key')
                        ->password()
                        ->revealable()
                        ->required(fn (Get $get): bool => $get('driver') === CourierProvider::DRIVER_STEADFAST)
                        ->maxLength(255),
                    TextInput::make('credentials.secret_key')
                        ->label('Secret Key')
                        ->password()
                        ->revealable()
                        ->required(fn (Get $get): bool => $get('driver') === CourierProvider::DRIVER_STEADFAST)
                        ->maxLength(255),
                    TextInput::make('credentials.webhook_secret')
                        ->label('Webhook Signing Secret')
                        ->password()
                        ->revealable()
                        ->maxLength(255),
                    TextInput::make('settings.signature_header')
                        ->label('Webhook Signature Header')
                        ->default('X-Courier-Signature')
                        ->maxLength(100),
                    TextInput::make('settings.tracking_url')
                        ->label('Tracking URL Template')
                        ->placeholder('https://example.com/track/{tracking_id}')
                        ->helperText('Use {tracking_id} as the placeholder.'),
                    TextInput::make('settings.label_url')
                        ->label('Label URL Template')
                        ->placeholder('https://example.com/label/{reference}')
                        ->helperText('Use {tracking_id} or {reference}.'),
                ])
                ->columns(2)
                ->visible(fn (Get $get): bool => in_array($get('driver'), CourierProvider::API_DRIVERS, true))
                ->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('driver')
                    ->label('Partner')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => CourierProvider::DRIVERS[$state ?? ''] ?? str($state)->headline()->toString()),
                TextColumn::make('settings.contact_person')
                    ->label('Contact Person')
                    ->placeholder('-'),
                TextColumn::make('settings.phone')
                    ->label('Phone Number')
                    ->placeholder('-'),
                TextColumn::make('settings.warehouse')
                    ->label('Warehouse')
                    ->placeholder('-'),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function canViewAny(): bool
    {
        return SchemaFacade::hasTable('courier_providers') && (Auth::user()?->hasPermission('sales.view') ?? false);
    }

    public static function canCreate(): bool
    {
        return SchemaFacade::hasTable('courier_providers')
            && (app(CompanyContext::class)->hasCompany()
                || (app(CompanyContext::class)->isAllCompanies() && (Auth::user()?->isSuperAdmin() ?? false)))
            && (Auth::user()?->hasPermission('sales.update') ?? false);
    }

    public static function canEdit(Model $record): bool
    {
        return SchemaFacade::hasTable('courier_providers') && (Auth::user()?->hasPermission('sales.update') ?? false);
    }

    public static function canDelete(Model $record): bool
    {
        return SchemaFacade::hasTable('courier_providers') && (Auth::user()?->isSuperAdmin() ?? false);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCourierProviders::route('/'),
            'create' => CreateCourierProvider::route('/create'),
            'edit' => EditCourierProvider::route('/{record}/edit'),
        ];
    }
}
