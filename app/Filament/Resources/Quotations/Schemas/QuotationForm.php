<?php

namespace App\Filament\Resources\Quotations\Schemas;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Quotation;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class QuotationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Quotation')
                    ->columnSpanFull()
                    ->schema([
                        Select::make('lead_id')
                            ->label('Lead')
                            ->relationship('lead', 'name')
                            ->searchable(['name', 'phone'])
                            ->preload(),

                        Select::make('customer_id')
                            ->label('Customer')
                            ->relationship('customer', 'name')
                            ->searchable(['name', 'phone'])
                            ->preload()
                            ->helperText('Leave blank for a new lead — the lead is converted to a customer on order.'),

                        Select::make('status')
                            ->options(Quotation::STATUSES)
                            ->default('draft')
                            ->required()
                            ->native(false),

                        DatePicker::make('valid_until')
                            ->label('Valid Until')
                            ->default(now()->addDays(7)),
                    ])
                    ->columns(2),

                Section::make('Items')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->schema([
                                Select::make('product_id')
                                    ->label('Product')
                                    ->relationship('product', 'name', fn ($query) => $query->where('is_active', true))
                                    ->searchable()
                                    ->live()
                                    ->required()
                                    ->afterStateUpdated(function (Get $get, Set $set, $state): void {
                                        $product = Product::find($state);

                                        if (! $product) {
                                            return;
                                        }

                                        $set('product_variant_id', null);
                                        $set('variant_label', null);
                                        $set('unit_price', $product->selling_price);
                                        $set('subtotal', (int) ($get('quantity') ?? 0) * (float) $product->selling_price);
                                        self::setQuotationTotals($get, $set, '../../');
                                    }),

                                Select::make('product_variant_id')
                                    ->label('Variation')
                                    ->options(function (Get $get): array {
                                        $product = Product::query()->with('activeVariants')->find($get('product_id'));

                                        if (! $product?->has_variants) {
                                            return [];
                                        }

                                        return $product->activeVariants
                                            ->mapWithKeys(fn (ProductVariant $variant): array => [
                                                $variant->getKey() => $variant->label(),
                                            ])
                                            ->all();
                                    })
                                    ->visible(fn (Get $get): bool => (bool) Product::query()->whereKey($get('product_id'))->value('has_variants'))
                                    ->live()
                                    ->afterStateUpdated(function (Get $get, Set $set, $state): void {
                                        $variant = ProductVariant::query()->find($state);

                                        if (! $variant) {
                                            $set('variant_label', null);

                                            return;
                                        }

                                        $set('variant_label', $variant->label());
                                        $set('unit_price', $variant->effectiveSalePrice());
                                        $set('subtotal', (int) ($get('quantity') ?? 0) * (float) $variant->effectiveSalePrice());
                                        self::setQuotationTotals($get, $set, '../../');
                                    }),

                                Hidden::make('variant_label'),

                                TextInput::make('quantity')
                                    ->integer()
                                    ->minValue(1)
                                    ->default(1)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set): void {
                                        $set('subtotal', (int) ($get('quantity') ?? 0) * (float) ($get('unit_price') ?? 0));
                                        self::setQuotationTotals($get, $set, '../../');
                                    }),

                                TextInput::make('unit_price')
                                    ->numeric()
                                    ->prefix('BDT')
                                    ->minValue(0)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set): void {
                                        $set('subtotal', (int) ($get('quantity') ?? 0) * (float) ($get('unit_price') ?? 0));
                                        self::setQuotationTotals($get, $set, '../../');
                                    }),

                                TextInput::make('subtotal')
                                    ->numeric()
                                    ->prefix('BDT')
                                    ->readOnly(),
                            ])
                            ->columns(4)
                            ->defaultItems(1)
                            ->addActionLabel('Add item')
                            ->reorderable(false)
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::setQuotationTotals($get, $set))
                            ->columnSpanFull(),
                    ]),

                Section::make('Totals')
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('discount_amount')
                            ->label('Discount')
                            ->numeric()
                            ->prefix('BDT')
                            ->default(0)
                            ->minValue(0)
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::setQuotationTotals($get, $set)),

                        TextInput::make('total_amount')
                            ->label('Total')
                            ->numeric()
                            ->prefix('BDT')
                            ->readOnly(),
                    ])
                    ->columns(2),
            ]);
    }

    protected static function setQuotationTotals(Get $get, Set $set, string $prefix = ''): void
    {
        $items = $get($prefix.'items') ?? [];
        $subtotal = collect($items)
            ->sum(fn (array $item): float => (int) ($item['quantity'] ?? 0) * (float) ($item['unit_price'] ?? 0));

        $set($prefix.'total_amount', max($subtotal - (float) ($get($prefix.'discount_amount') ?? 0), 0));
    }
}
