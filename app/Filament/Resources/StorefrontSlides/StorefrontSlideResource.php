<?php

namespace App\Filament\Resources\StorefrontSlides;

use App\Filament\Concerns\OptimizesUploadedImages;
use App\Filament\Resources\StorefrontSlides\Pages\CreateStorefrontSlide;
use App\Filament\Resources\StorefrontSlides\Pages\EditStorefrontSlide;
use App\Filament\Resources\StorefrontSlides\Pages\ListStorefrontSlides;
use App\Models\Product;
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
use Filament\Schemas\Components\Utilities\Get;
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
    use OptimizesUploadedImages;

    protected static ?string $model = StorefrontSlide::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|UnitEnum|null $navigationGroup = 'Storefront';

    protected static ?int $navigationSort = 0;

    protected static ?string $navigationLabel = 'Hero Slides';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Slide')
                ->columnSpanFull()
                ->schema([
                    Select::make('company_id')
                        ->relationship('company', 'name')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->live(),
                    Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),
                    FileUpload::make('image')
                        ->label('Image (desktop)')
                        ->helperText('Recommended: wide banner, at least 1600x600px. Automatically compressed to WebP on upload.')
                        ->image()
                        ->maxSize(2048)
                        ->disk('public')
                        ->directory('storefront/slides')
                        ->imageEditor()
                        ->saveUploadedFileUsing(static::optimizeImageUpload())
                        ->required(),
                    FileUpload::make('image_mobile')
                        ->label('Image (mobile)')
                        ->helperText('Optional. Vertical/square image shown on phones instead of the desktop image. Automatically compressed to WebP on upload.')
                        ->image()
                        ->maxSize(2048)
                        ->disk('public')
                        ->directory('storefront/slides')
                        ->saveUploadedFileUsing(static::optimizeImageUpload()),
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
                    Select::make('product_id')
                        ->label('Link to product (optional)')
                        ->helperText('Clicking the slide image sends visitors to this product\'s page. The CTA URL above wins if both are set.')
                        ->searchable()
                        ->options(function (Get $get, ?StorefrontSlide $record): array {
                            $companyId = $get('company_id') ?? $record?->company_id;

                            return $companyId
                                ? Product::withoutGlobalScopes()->where('company_id', $companyId)->orderBy('name')->limit(100)->pluck('name', 'id')->all()
                                : [];
                        })
                        ->getSearchResultsUsing(function (string $search, Get $get, ?StorefrontSlide $record) {
                            $companyId = $get('company_id') ?? $record?->company_id;

                            return $companyId
                                ? Product::withoutGlobalScopes()->where('company_id', $companyId)->where('name', 'like', "%{$search}%")->limit(50)->pluck('name', 'id')
                                : [];
                        })
                        ->getOptionLabelUsing(fn ($value): ?string => Product::withoutGlobalScopes()->find($value)?->name),
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
