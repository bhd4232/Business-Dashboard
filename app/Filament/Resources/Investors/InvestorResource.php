<?php

namespace App\Filament\Resources\Investors;

use App\Filament\Clusters\Investments;
use App\Filament\Resources\Investors\Pages\CreateInvestor;
use App\Filament\Resources\Investors\Pages\EditInvestor;
use App\Filament\Resources\Investors\Pages\ListInvestors;
use App\Filament\Resources\Investors\Pages\ViewInvestor;
use App\Filament\Resources\Investors\RelationManagers\InvestmentsRelationManager;
use App\Models\Investor;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class InvestorResource extends Resource
{
    protected static ?string $model = Investor::class;

    protected static ?string $cluster = Investments::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Investor Identity')->schema([
                TextInput::make('name')->required(),
                TextInput::make('guardian_name')->label('Father / Spouse Name'),
                TextInput::make('phone')->tel()->required()->unique(ignoreRecord: true),
                TextInput::make('email')->email(),
                TextInput::make('nid_number')->label('NID Number'),
                Select::make('channel_partner_id')
                    ->label('Channel Partner')
                    ->relationship('channelPartner', 'name', fn ($query, ?Investor $record) => $query->when($record, fn ($q) => $q->whereKeyNot($record->getKey())))
                    ->searchable()->preload()
                    ->disabled(fn (?Investor $record): bool => filled($record?->channel_partner_id) && ! (auth()->user()?->hasPermission('investments.manage_channel_partner') ?? false))
                    ->dehydrated(),
                Textarea::make('channel_partner_change_reason')->label('Channel Partner Change Reason')
                    ->required(fn (?Investor $record): bool => filled($record?->channel_partner_id) && (auth()->user()?->hasPermission('investments.manage_channel_partner') ?? false))
                    ->visible(fn (?Investor $record): bool => filled($record?->channel_partner_id) && (auth()->user()?->hasPermission('investments.manage_channel_partner') ?? false))
                    ->columnSpanFull(),
                Textarea::make('address')->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Investor Summary')->schema([
                TextEntry::make('name'),
                TextEntry::make('guardian_name')->placeholder('-'),
                TextEntry::make('phone'),
                TextEntry::make('email')->placeholder('-'),
                TextEntry::make('nid_number')->label('NID Number')->placeholder('-'),
                TextEntry::make('channelPartner.name')->label('Channel Partner')->placeholder('Direct investor'),
                TextEntry::make('lifetime_invested')->state(fn (Investor $record): float => $record->totalInvestedLifetime())->money('BDT'),
                TextEntry::make('lifetime_profit')->state(fn (Investor $record): float => $record->totalProfitReceivedLifetime())->money('BDT'),
                TextEntry::make('address')->columnSpanFull()->placeholder('-'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->modifyQueryUsing(fn ($query) => $query->withSum('investments', 'amount'))->columns([
            TextColumn::make('name')->searchable()->sortable(),
            TextColumn::make('phone')->searchable(),
            TextColumn::make('nid_number')->label('NID')->searchable()->placeholder('-'),
            TextColumn::make('channelPartner.name')->label('Channel Partner')->placeholder('Direct'),
            TextColumn::make('investments_sum_amount')->label('Lifetime Invested')->money('BDT'),
        ])->recordActions([ViewAction::make(), EditAction::make()]);
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasPermission('investments.manage') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->hasPermission('investments.manage') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return $record instanceof Investor && ! $record->investments()->exists() && (auth()->user()?->hasPermission('investments.manage') ?? false);
    }

    public static function getRelations(): array
    {
        return [InvestmentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return ['index' => ListInvestors::route('/'), 'create' => CreateInvestor::route('/create'), 'view' => ViewInvestor::route('/{record}'), 'edit' => EditInvestor::route('/{record}/edit')];
    }
}
