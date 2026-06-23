<?php

namespace App\Filament\Resources\Companies;

use App\Filament\Resources\Companies\Pages\CreateCompany;
use App\Filament\Resources\Companies\Pages\EditCompany;
use App\Filament\Resources\Companies\Pages\ListCompanies;
use App\Filament\Resources\Companies\Pages\ViewCompany;
use App\Models\Company;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use UnitEnum;

class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = 'Company Management';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Company Profile')
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('slug')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),
                    TextInput::make('business_type')
                        ->maxLength(255),
                    TextInput::make('invoice_prefix')
                        ->required()
                        ->maxLength(20),
                    Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),
                ])
                ->columns(2),

            Section::make('Branding and Contact')
                ->schema([
                    FileUpload::make('logo')
                        ->image()
                        ->disk('public')
                        ->directory('companies')
                        ->imageEditor()
                        ->downloadable()
                        ->openable(),
                    TextInput::make('phone')
                        ->tel()
                        ->maxLength(255),
                    TextInput::make('email')
                        ->email()
                        ->maxLength(255),
                    Textarea::make('address')
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Section::make('Localization')
                ->schema([
                    TextInput::make('currency')
                        ->default('BDT')
                        ->required()
                        ->maxLength(12),
                    Select::make('timezone')
                        ->options([
                            'Asia/Dhaka' => 'Asia/Dhaka',
                            'UTC' => 'UTC',
                            'Asia/Dubai' => 'Asia/Dubai',
                            'Asia/Kolkata' => 'Asia/Kolkata',
                            'Asia/Shanghai' => 'Asia/Shanghai',
                            'Europe/London' => 'Europe/London',
                            'America/New_York' => 'America/New_York',
                        ])
                        ->default('Asia/Dhaka')
                        ->required()
                        ->searchable(),
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
                    ->height(40)
                    ->square()
                    ->toggleable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('business_type')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('invoice_prefix')
                    ->badge()
                    ->searchable(),
                TextColumn::make('currency')
                    ->badge(),
                TextColumn::make('timezone')
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            ImageEntry::make('logo')
                ->disk('public'),
            TextEntry::make('name'),
            TextEntry::make('slug'),
            TextEntry::make('business_type'),
            TextEntry::make('invoice_prefix')
                ->badge(),
            TextEntry::make('phone'),
            TextEntry::make('email'),
            TextEntry::make('address'),
            TextEntry::make('currency')
                ->badge(),
            TextEntry::make('timezone'),
            IconEntry::make('is_active')
                ->boolean(),
        ]);
    }

    public static function canViewAny(): bool
    {
        return SchemaFacade::hasTable('companies') && (Auth::user()?->canManageSettings() ?? false);
    }

    public static function canCreate(): bool
    {
        return SchemaFacade::hasTable('companies') && (Auth::user()?->canManageSettings() ?? false);
    }

    public static function canEdit($record): bool
    {
        return SchemaFacade::hasTable('companies') && (Auth::user()?->canManageSettings() ?? false);
    }

    public static function canDelete($record): bool
    {
        return SchemaFacade::hasTable('companies') && (Auth::user()?->isSuperAdmin() ?? false);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCompanies::route('/'),
            'create' => CreateCompany::route('/create'),
            'view' => ViewCompany::route('/{record}'),
            'edit' => EditCompany::route('/{record}/edit'),
        ];
    }
}
