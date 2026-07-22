<?php

namespace App\Filament\Resources\FundSources;

use App\Filament\Clusters\Finance;
use App\Filament\Resources\FundSources\Pages\CreateFundSource;
use App\Filament\Resources\FundSources\Pages\EditFundSource;
use App\Filament\Resources\FundSources\Pages\ListFundSources;
use App\Models\FundSource;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class FundSourceResource extends Resource
{
    protected static ?string $model = FundSource::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $cluster = Finance::class;

    protected static ?string $navigationLabel = 'Fund Sources';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Fund Source')->columnSpanFull()->schema([
                TextInput::make('name')->required()->maxLength(255),
                Select::make('type')->options(FundSource::TYPES)->required()->live()->default('cash'),
                Select::make('account_id')
                    ->relationship('account', 'name')
                    ->label('Linked Account')
                    ->helperText('Required for cash/bank/mobile banking/wallet/petty cash types — balance is always read from this account.')
                    ->visible(fn (Get $get) => in_array($get('type'), FundSource::ACCOUNT_LINKED_TYPES, true))
                    ->required(fn (Get $get) => in_array($get('type'), FundSource::ACCOUNT_LINKED_TYPES, true)),
                TextInput::make('opening_balance')
                    ->numeric()->prefix('BDT')->default(0)
                    ->helperText('Only used for capital-type sources (investment/profit/loan/credit) that do not have a linked account.')
                    ->visible(fn (Get $get) => ! in_array($get('type'), FundSource::ACCOUNT_LINKED_TYPES, true)),
                Toggle::make('is_active')->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('type')->badge()->formatStateUsing(fn (string $state) => FundSource::TYPES[$state] ?? $state),
                TextColumn::make('account.name')->label('Linked Account')->placeholder('—'),
                TextColumn::make('balance')->label('Balance')->state(fn (FundSource $record) => $record->balance())->money('BDT'),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->filters([SelectFilter::make('type')->options(FundSource::TYPES)])
            ->recordActions([EditAction::make()]);
    }

    public static function canViewAny(): bool
    {
        return Auth::user()?->canManageFundSources() ?? false;
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->canManageFundSources() ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return Auth::user()?->canManageFundSources() ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return Auth::user()?->canDeleteSensitiveRecords() ?? false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFundSources::route('/'),
            'create' => CreateFundSource::route('/create'),
            'edit' => EditFundSource::route('/{record}/edit'),
        ];
    }
}
