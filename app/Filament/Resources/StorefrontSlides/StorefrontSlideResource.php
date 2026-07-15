<?php

namespace App\Filament\Resources\StorefrontSlides;

use App\Filament\Resources\StorefrontSlides\Pages\CreateStorefrontSlide;
use App\Filament\Resources\StorefrontSlides\Pages\EditStorefrontSlide;
use App\Filament\Resources\StorefrontSlides\Pages\ListStorefrontSlides;
use App\Models\StorefrontSlide;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class StorefrontSlideResource extends Resource
{
    protected static ?string $model = StorefrontSlide::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|UnitEnum|null $navigationGroup = 'Storefront';

    protected static ?int $navigationSort = 0;

    protected static ?string $navigationLabel = 'Hero Slides';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Slide')
                ->schema([
                    Select::make('company_id')
                        ->relationship('company', 'name')
                        ->required()
                        ->searchable()
                        ->preload(),
                    Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),
                    FileUpload::make('image')
                        ->label('Image (desktop)')
                        ->helperText('Recommended: wide banner, at least 1600x600px.')
                        ->image()
                        ->maxSize(2048)
                        ->disk('public')
                        ->directory('storefront/slides')
                        ->imageEditor()
                        ->required(),
                    FileUpload::make('image_mobile')
                        ->label('Image (mobile)')
                        ->helperText('Optional. Vertical/square image shown on phones instead of the desktop image.')
                        ->image()
                        ->maxSize(2048)
                        ->disk('public')
                        ->directory('storefront/slides'),
                    TextInput::make('heading')
                        ->maxLength(120),
                    TextInput::make('subheading')
                        ->maxLength(200),
                    TextInput::make('cta_label')
                        ->maxLength(40),
                    TextInput::make('cta_url')
                        ->url()
                        ->maxLength(255)
                        ->helperText('Full URL or a relative path such as /products.'),
                    TextInput::make('sort_order')
                        ->numeric()
                        ->default(0)
                        ->minValue(0),
                    DateTimePicker::make('starts_at')
                        ->helperText('Optional. Slide is hidden before this time.'),
                    DateTimePicker::make('ends_at')
                        ->helperText('Optional. Slide is hidden after this time.'),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')
                    ->label('Image'),
                TextColumn::make('heading')
                    ->searchable(),
                TextColumn::make('company.name')
                    ->label('Company')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('sort_order')
                    ->sortable(),
                TextColumn::make('starts_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('ends_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
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
        return Auth::user()?->canManageSettings() ?? false;
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->canManageSettings() ?? false;
    }

    public static function canEdit($record): bool
    {
        return Auth::user()?->canManageSettings() ?? false;
    }

    public static function canDelete($record): bool
    {
        return Auth::user()?->isSuperAdmin() ?? false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStorefrontSlides::route('/'),
            'create' => CreateStorefrontSlide::route('/create'),
            'edit' => EditStorefrontSlide::route('/{record}/edit'),
        ];
    }
}
