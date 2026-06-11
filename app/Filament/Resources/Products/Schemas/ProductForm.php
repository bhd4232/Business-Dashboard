<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Category;
use App\Models\Product;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic Information')
                    ->schema([
                        TextInput::make('name')
                            ->label('Product Name')
                            ->required()
                            ->maxLength(255),

                        Select::make('category_id')
                            ->label('Category')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->label('Category Name')
                                    ->required()
                                    ->maxLength(255),

                                Textarea::make('description')
                                    ->rows(3)
                                    ->columnSpanFull(),

                                Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true),
                            ])
                            ->createOptionUsing(function (array $data): int {
                                $slug = Str::slug($data['name']);
                                $originalSlug = $slug;
                                $suffix = 2;

                                while (Category::query()->where('slug', $slug)->exists()) {
                                    $slug = "{$originalSlug}-{$suffix}";
                                    $suffix++;
                                }

                                return Category::query()->create([
                                    'name' => $data['name'],
                                    'slug' => $slug,
                                    'description' => $data['description'] ?? null,
                                    'is_active' => $data['is_active'] ?? true,
                                ])->getKey();
                            })
                            ->required(),

                        TextInput::make('sku')
                            ->label('SKU')
                            ->required()
                            ->unique(ignoreRecord: true),

                        TextInput::make('barcode')
                            ->label('Barcode')
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        TextInput::make('brand')
                            ->label('Brand')
                            ->maxLength(255),

                        TextInput::make('unit')
                            ->label('Unit')
                            ->default('pcs')
                            ->required()
                            ->maxLength(50),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),

                        Select::make('status')
                            ->label('Status')
                            ->options(Product::STATUSES)
                            ->default(Product::STATUS_AVAILABLE)
                            ->required(),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->persistCollapsed(),

                Section::make('Pricing and Stock')
                    ->schema([
                        TextInput::make('cost_price')
                            ->label('Cost Price')
                            ->numeric()
                            ->prefix('BDT')
                            ->minValue(0),

                        TextInput::make('sale_price')
                            ->label('Sale Price')
                            ->numeric()
                            ->prefix('BDT')
                            ->minValue(0)
                            ->required(),

                        TextInput::make('stock')
                            ->label('Stock Quantity')
                            ->integer()
                            ->default(0)
                            ->minValue(0)
                            ->required()
                            ->helperText('Saving this value creates an opening or adjustment stock movement.'),

                        TextInput::make('reorder_level')
                            ->label('Reorder Level')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->required(),

                        TextInput::make('vat_rate')
                            ->label('VAT Rate')
                            ->numeric()
                            ->suffix('%')
                            ->default(0)
                            ->minValue(0)
                            ->maxValue(100)
                            ->required(),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->persistCollapsed(),

                Section::make('Product Image')
                    ->schema([
                        FileUpload::make('image')
                            ->label('Upload Image')
                            ->image()
                            ->disk('public')
                            ->directory('products')
                            ->imageEditor()
                            ->downloadable()
                            ->openable(),
                    ])
                    ->collapsible()
                    ->persistCollapsed(),

                Section::make('Description')
                    ->schema([
                        Textarea::make('description')
                            ->label('Description')
                            ->rows(4)
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->persistCollapsed(),
            ]);
    }
}
