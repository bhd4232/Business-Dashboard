<?php

namespace App\Filament\Resources\CustomerBlacklists;

use App\Filament\Clusters\CustomerSuccess;
use App\Filament\Resources\CustomerBlacklists\Pages\CreateCustomerBlacklist;
use App\Filament\Resources\CustomerBlacklists\Pages\EditCustomerBlacklist;
use App\Filament\Resources\CustomerBlacklists\Pages\ListCustomerBlacklists;
use App\Models\CustomerBlacklist;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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
use Illuminate\Support\Facades\Schema as SchemaFacade;

class CustomerBlacklistResource extends Resource
{
    protected static ?string $model = CustomerBlacklist::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNoSymbol;

    protected static ?string $cluster = CustomerSuccess::class;

    protected static ?string $navigationLabel = 'Blacklists';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Blacklist Entry')->schema([
                Select::make('company_id')->relationship('company', 'name')->placeholder('All companies (global)'),
                TextInput::make('phone')->tel()->maxLength(40)->helperText('Provide phone, address, or both.'),
                Textarea::make('address')->rows(3),
                Textarea::make('reason')->required()->rows(3),
                Toggle::make('is_active')->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('company.name')->label('Scope')->placeholder('All Companies'),
            TextColumn::make('phone')->searchable()->placeholder('-'),
            TextColumn::make('address')->limit(40)->placeholder('-'),
            TextColumn::make('reason')->limit(50),
            IconColumn::make('is_active')->boolean(),
            TextColumn::make('creator.name')->label('Added By')->placeholder('System'),
            TextColumn::make('created_at')->dateTime()->sortable(),
        ])->recordActions([EditAction::make()]);
    }

    public static function canViewAny(): bool
    {
        return SchemaFacade::hasTable('customer_blacklists') && (Auth::user()?->isSuperAdmin() ?? false);
    }

    public static function canCreate(): bool
    {
        return SchemaFacade::hasTable('customer_blacklists') && (Auth::user()?->isSuperAdmin() ?? false);
    }

    public static function canEdit($record): bool
    {
        return SchemaFacade::hasTable('customer_blacklists') && (Auth::user()?->isSuperAdmin() ?? false);
    }

    public static function canDelete($record): bool
    {
        return SchemaFacade::hasTable('customer_blacklists') && (Auth::user()?->isSuperAdmin() ?? false);
    }

    public static function getPages(): array
    {
        return ['index' => ListCustomerBlacklists::route('/'), 'create' => CreateCustomerBlacklist::route('/create'), 'edit' => EditCustomerBlacklist::route('/{record}/edit')];
    }
}
