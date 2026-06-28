<?php

namespace App\Filament\Resources\StorefrontSettings;

use App\Filament\Resources\StorefrontSettings\Pages\CreateStorefrontSetting;
use App\Filament\Resources\StorefrontSettings\Pages\EditStorefrontSetting;
use App\Filament\Resources\StorefrontSettings\Pages\ListStorefrontSettings;
use App\Models\StorefrontSetting;
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
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema as SchemaFacade;
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
                        ->unique(table: 'storefront_settings', column: 'company_id', ignoreRecord: true),
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
                IconColumn::make('is_published')
                    ->label('Published')
                    ->boolean()
                    ->sortable(),
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
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
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
