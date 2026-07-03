<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Category;
use App\Models\Product;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
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
                            ->required(fn ($get): bool => ! $get('has_variants'))
                            ->disabled(fn ($get): bool => (bool) $get('has_variants'))
                            ->dehydrated(fn ($get): bool => ! $get('has_variants'))
                            ->helperText('Saving this value creates an opening or adjustment stock movement. When variations are enabled, stock is tracked per variation and this field becomes the automatic sum of variation stock.'),

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

                Section::make('Product Images')
                    ->schema([
                        FileUpload::make('image')
                            ->label('Featured Image')
                            ->helperText('Main image shown in product lists and as the default on the product page. Recommended: square, at least 800x800px.')
                            ->image()
                            ->maxSize(2048)
                            ->disk('public')
                            ->directory('products')
                            ->imageEditor()
                            ->downloadable()
                            ->openable(),
                        FileUpload::make('gallery_images')
                            ->label('Gallery Images')
                            ->helperText('Additional product photos shown as a gallery on the product page. Drag to reorder.')
                            ->image()
                            ->multiple()
                            ->reorderable()
                            ->maxSize(2048)
                            ->maxFiles(10)
                            ->disk('public')
                            ->directory('products/gallery')
                            ->imageEditor()
                            ->downloadable()
                            ->openable(),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->persistCollapsed(),

                Section::make('Variations')
                    ->description('Enable for products that come in multiple options (size, color, model). Each variation has its own SKU, price, stock, and images — like WooCommerce variable products.')
                    ->schema([
                        Toggle::make('has_variants')
                            ->label('This product has variations')
                            ->live()
                            ->default(false),
                        Repeater::make('variants')
                            ->relationship('variants')
                            ->visible(fn ($get): bool => (bool) $get('has_variants'))
                            ->schema([
                                KeyValue::make('options')
                                    ->label('Options')
                                    ->keyLabel('Attribute (e.g. Size, Color)')
                                    ->valueLabel('Value (e.g. M, Red)')
                                    ->required()
                                    ->columnSpanFull(),
                                TextInput::make('sku')
                                    ->label('Variation SKU')
                                    ->maxLength(100),
                                TextInput::make('sale_price')
                                    ->label('Sale Price')
                                    ->numeric()
                                    ->prefix('BDT')
                                    ->minValue(0)
                                    ->helperText('Leave empty to use the product sale price.'),
                                TextInput::make('cost_price')
                                    ->label('Cost Price')
                                    ->numeric()
                                    ->prefix('BDT')
                                    ->minValue(0),
                                TextInput::make('stock')
                                    ->label('Stock')
                                    ->integer()
                                    ->default(0)
                                    ->minValue(0)
                                    ->required(),
                                Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true),
                                FileUpload::make('images')
                                    ->label('Variation Images')
                                    ->helperText('Shown when this variation is selected on the storefront.')
                                    ->image()
                                    ->multiple()
                                    ->reorderable()
                                    ->maxSize(2048)
                                    ->maxFiles(6)
                                    ->disk('public')
                                    ->directory('products/variants')
                                    ->imageEditor()
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->orderColumn('sort_order')
                            ->reorderable()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => collect($state['options'] ?? [])->map(fn ($v, $k) => "$k: $v")->implode(' / ') ?: null)
                            ->addActionLabel('Add variation')
                            ->defaultItems(0),
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
