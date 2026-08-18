<?php

namespace App\Filament\Resources\MetaAdAccounts;

use App\Filament\Clusters\Ads;
use App\Filament\Resources\MetaAdAccounts\Pages\CreateMetaAdAccount;
use App\Filament\Resources\MetaAdAccounts\Pages\EditMetaAdAccount;
use App\Filament\Resources\MetaAdAccounts\Pages\ListMetaAdAccounts;
use App\Models\Company;
use App\Models\MetaAdAccount;
use App\Services\CompanyContext;
use App\Services\MetaMarketingApiClient;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Meta Ads account credentials — App ID/Secret/Access Token/Ad Account ID,
 * the same fields Nuport's own "Meta Ads Account Settings" screen asks for
 * (Warehouses is the one Nuport field skipped: this app has no warehouse
 * concept). Encrypted-credentials form mirrors CourierProviderResource.
 */
class MetaAdAccountResource extends Resource
{
    protected static ?string $model = MetaAdAccount::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?string $cluster = Ads::class;

    protected static ?string $navigationLabel = 'Ad Accounts';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Meta Ads Account')
                ->columnSpanFull()
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
                        ->helperText('Select the company that will own this ad account.'),
                    TextInput::make('name')
                        ->label('Account Label')
                        ->placeholder('e.g. Main Store')
                        ->required()
                        ->maxLength(255),
                    Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),
                    Toggle::make('is_default')
                        ->label('Set as default ad account')
                        ->helperText('The Meta Ads dashboard opens to this account when a company has more than one. Only one account per company can be default.')
                        ->default(false),
                ])
                ->columns(2)
                ->collapsible(),

            Section::make('API Credentials')
                ->columnSpanFull()
                ->description('From Meta Business Settings > System Users (or an app you control) with ads_management/ads_read permission on this ad account.')
                ->schema([
                    TextInput::make('credentials.app_id')
                        ->label('App ID')
                        ->required()
                        ->maxLength(255)
                        ->hintIcon(Heroicon::OutlinedInformationCircle)
                        ->hintIconTooltip('developers.facebook.com → My Apps → select your app → Settings > Basic. The "App ID" is shown at the top of that page.'),
                    TextInput::make('credentials.app_secret')
                        ->label('App Secret')
                        ->password()
                        ->revealable()
                        ->required(fn (?MetaAdAccount $record): bool => $record === null)
                        ->maxLength(255)
                        ->hintIcon(Heroicon::OutlinedInformationCircle)
                        ->hintIconTooltip('Same page as App ID: developers.facebook.com → My Apps → your app → Settings > Basic. Click "Show" next to "App Secret" (it may ask you to re-enter your Facebook password).')
                        ->helperText(fn (?MetaAdAccount $record): ?string => $record
                            ? 'Never shown again once saved — leave blank to keep the existing value.'
                            : null),
                    TextInput::make('credentials.access_token')
                        ->label('Access Token')
                        ->password()
                        ->revealable()
                        ->required(fn (?MetaAdAccount $record): bool => $record === null)
                        ->maxLength(2000)
                        ->hintIcon(Heroicon::OutlinedInformationCircle)
                        ->hintIconTooltip('business.facebook.com/settings → System Users → select/create a System User → "Generate New Token" → pick your app → tick ads_management + ads_read → Generate Token. Copy the full token (usually starts with "EAA..." and is long) — it is shown only once.')
                        ->helperText(fn (?MetaAdAccount $record): string => $record
                            ? 'A long-lived System User or Page access token with ads permissions. Also assign it access to your Pixel under Business Settings > Data Sources for the Ads > Events Manager page to work. Never shown again once saved — leave blank to keep the existing value.'
                            : 'A long-lived System User or Page access token with ads permissions.'),
                    TextInput::make('credentials.ad_account_id')
                        ->label('Ad Account ID')
                        ->placeholder('act_123456789 or 123456789')
                        ->required()
                        ->maxLength(64)
                        ->hintIcon(Heroicon::OutlinedInformationCircle)
                        ->hintIconTooltip('business.facebook.com/settings → Accounts > Ad Accounts → select the ad account. The ID is shown right under its name (e.g. act_123456789 — with or without the "act_" prefix, both work here).'),
                    TextInput::make('credentials.page_id')
                        ->label('Facebook Page ID')
                        ->placeholder('e.g. 100064012345678')
                        ->maxLength(64)
                        ->hintIcon(Heroicon::OutlinedInformationCircle)
                        ->hintIconTooltip('business.facebook.com/settings → Accounts > Pages → select the Page. The Page ID is shown under its name. (Or on the Page itself: About → Page Transparency.)')
                        ->helperText('Only needed to create new ads (Meta Business Settings > Accounts > Pages). Not required for viewing/syncing/pausing existing campaigns.'),
                ])
                ->columns(2)
                ->collapsible(),

            Section::make('Currency')
                ->columnSpanFull()
                ->schema([
                    TextInput::make('currency')
                        ->label('Ad Account Currency')
                        ->placeholder('USD')
                        ->maxLength(8)
                        ->helperText('Filled in automatically the first time Test Connection succeeds; you can also set it manually.'),
                    TextInput::make('exchange_rate_to_company_currency')
                        ->label('Exchange Rate to Company Currency')
                        ->numeric()
                        ->minValue(0)
                        ->helperText('Only needed when the ad account currency differs from the company currency.'),
                ])
                ->columns(2)
                ->collapsible()
                ->collapsed(),

            Section::make('AI Marketing Assistant Guardrails')
                ->columnSpanFull()
                ->description('Phase D: the AI assistant only ever proposes drafts (never spends on its own — see the Ads > AI Assistant page), but it is switched off entirely for this account until a Max Daily Budget is set here. There is no default spending ceiling.')
                ->schema([
                    TextInput::make('ai_daily_budget_min')
                        ->label('Min Daily Budget')
                        ->numeric()
                        ->minValue(0)
                        ->helperText('In this account\'s own currency. Leave blank for no floor.'),
                    TextInput::make('ai_daily_budget_max')
                        ->label('Max Daily Budget')
                        ->numeric()
                        ->minValue(1)
                        // Not ->gte('ai_daily_budget_min'): Laravel's gte:field
                        // rule fails outright whenever the compared field is
                        // blank (it can't be treated as "no floor" — see
                        // Illuminate\Validation\Concerns\ValidatesAttributes::
                        // validateGte()), but Min Daily Budget is meant to be
                        // optional. This custom rule only compares when both
                        // are actually filled.
                        ->rule(fn (Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                            $min = $get('ai_daily_budget_min');

                            if (filled($min) && filled($value) && (float) $value < (float) $min) {
                                $fail('The Max Daily Budget must be greater than or equal to the Min Daily Budget.');
                            }
                        })
                        ->helperText('Required for the AI assistant to generate recommendations for this account.'),
                    TextInput::make('ai_max_duration_days')
                        ->label('Max Suggested Duration (days)')
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->maxValue(90)
                        ->default(30)
                        ->helperText('Advisory only — shown to you as guidance, not enforced on Meta (there is no scheduling field yet).'),
                ])
                ->columns(3)
                ->collapsible()
                ->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('currency')->placeholder('-'),
                IconColumn::make('is_active')->label('Active')->boolean(),
                IconColumn::make('is_default')->label('Default')->boolean(),
                TextColumn::make('last_synced_at')
                    ->label('Last Sync')
                    ->dateTime()
                    ->placeholder('Never')
                    ->toggleable(),
                TextColumn::make('sync_failure_count')
                    ->label('Sync Failures')
                    ->badge()
                    ->color(fn (?int $state): string => ($state ?? 0) > 0 ? 'danger' : 'success')
                    ->tooltip(fn (MetaAdAccount $record): ?string => $record->last_sync_error)
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Action::make('testConnection')
                    ->label('Test Connection')
                    ->icon(Heroicon::OutlinedSignal)
                    ->action(function (MetaAdAccount $record, MetaMarketingApiClient $client): void {
                        try {
                            $result = $client->verifyCredentials($record);

                            $record->forceFill([
                                'currency' => $result['currency'] ?? $record->currency,
                            ])->save();

                            Notification::make()
                                ->title('Connection successful')
                                ->body(($result['name'] ?? 'Ad account').' — '.($result['currency'] ?? '?').' — status '.($result['account_status'] ?? '?'))
                                ->success()
                                ->send();
                        } catch (Throwable $exception) {
                            Notification::make()
                                ->title('Connection failed')
                                ->body($exception instanceof ValidationException
                                    ? collect($exception->errors())->flatten()->implode(' ')
                                    : $exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
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
        return SchemaFacade::hasTable('meta_ad_accounts') && (Auth::user()?->hasPermission('marketing.view') ?? false);
    }

    public static function canCreate(): bool
    {
        return SchemaFacade::hasTable('meta_ad_accounts')
            && (app(CompanyContext::class)->hasCompany()
                || (app(CompanyContext::class)->isAllCompanies() && (Auth::user()?->isSuperAdmin() ?? false)))
            && (Auth::user()?->hasPermission('marketing.create') ?? false);
    }

    public static function canEdit(Model $record): bool
    {
        return SchemaFacade::hasTable('meta_ad_accounts') && (Auth::user()?->hasPermission('marketing.update') ?? false);
    }

    public static function canDelete(Model $record): bool
    {
        return SchemaFacade::hasTable('meta_ad_accounts') && (Auth::user()?->hasPermission('marketing.delete') ?? false);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMetaAdAccounts::route('/'),
            'create' => CreateMetaAdAccount::route('/create'),
            'edit' => EditMetaAdAccount::route('/{record}/edit'),
        ];
    }
}
