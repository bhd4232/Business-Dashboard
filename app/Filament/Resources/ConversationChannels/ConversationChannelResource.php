<?php

namespace App\Filament\Resources\ConversationChannels;

use App\Filament\Resources\ConversationChannels\Pages\CreateConversationChannel;
use App\Filament\Resources\ConversationChannels\Pages\EditConversationChannel;
use App\Filament\Resources\ConversationChannels\Pages\ListConversationChannels;
use App\Models\Company;
use App\Models\ConversationChannel;
use App\Services\CompanyContext;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class ConversationChannelResource extends Resource
{
    protected static ?string $model = ConversationChannel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static string|UnitEnum|null $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Chat Channel';

    protected static ?string $recordTitleAttribute = 'display_name';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Channel')->columnSpanFull()->schema([
                // In "All Companies" mode there is no active company for the
                // BelongsToCompany hook to assign, so the record would silently
                // fall back to the default company and "disappear" from the
                // owner's real company — make the target company explicit.
                \Filament\Forms\Components\Select::make('company_id')
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
                    ->helperText('Select the company that will own this chat channel.'),
                \Filament\Forms\Components\Select::make('provider')
                    ->options(ConversationChannel::PROVIDERS)
                    ->required()
                    ->native(false),
                TextInput::make('display_name')
                    ->label('Display Name')
                    ->placeholder('ZamZam Main Page')
                    ->required()
                    ->maxLength(255),
                TextInput::make('external_id')
                    ->label('Phone Number ID / Page ID')
                    ->helperText('WhatsApp: the WABA phone_number_id. Messenger: the Facebook page_id.')
                    ->required()
                    ->maxLength(255)
                    // The provider+external_id pair is globally unique (the
                    // webhook resolves a channel by external_id alone), so a
                    // duplicate must fail as a form message, not a DB 500.
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (\Illuminate\Validation\Rules\Unique $rule, \Filament\Schemas\Components\Utilities\Get $get) => $rule->where('provider', $get('provider')),
                    )
                    ->validationMessages([
                        'unique' => 'A chat channel with this Phone Number ID / Page ID already exists (possibly under another company).',
                    ]),
                Toggle::make('auto_create_leads')
                    ->label('Auto-create leads for unknown contacts')
                    ->default(true),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ])->columns(2),

            Section::make('Credentials')->columnSpanFull()->schema([
                TextInput::make('access_token')
                    ->label('Access Token')
                    ->password()
                    ->revealable()
                    ->helperText('Stored encrypted. WhatsApp: permanent WABA token. Messenger: page access token.'),
                TextInput::make('app_secret')
                    ->label('Meta App Secret')
                    ->password()
                    ->revealable()
                    ->helperText('Stored encrypted. Used to verify webhook signatures (X-Hub-Signature-256).'),
                TextInput::make('verify_token')
                    ->label('Webhook Verify Token')
                    ->helperText('Any secret string; paste the same value in the Meta webhook subscription form.'),
                TextInput::make('webhook_url')
                    ->label('Webhook URL')
                    ->default(fn (): string => route('webhooks.meta.handle'))
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Paste this callback URL into the Meta app webhook settings.'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('company.name')
                ->label('Company')
                ->visible(fn (): bool => app(CompanyContext::class)->isAllCompanies()),
            TextColumn::make('display_name')->label('Name')->searchable(),
            TextColumn::make('provider')
                ->badge()
                ->formatStateUsing(fn (?string $state): string => ConversationChannel::PROVIDERS[$state] ?? (string) $state),
            TextColumn::make('external_id')->label('External ID'),
            IconColumn::make('is_active')->label('Active')->boolean(),
            TextColumn::make('conversations_count')->counts('conversations')->label('Conversations'),
        ]);
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->isSuperAdmin() ?? false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConversationChannels::route('/'),
            'create' => CreateConversationChannel::route('/create'),
            'edit' => EditConversationChannel::route('/{record}/edit'),
        ];
    }
}
