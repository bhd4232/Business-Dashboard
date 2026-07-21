<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Product;
use App\Support\CompanyMedia;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Product Details')
                    ->columnSpanFull()
                    ->schema([
                        ImageEntry::make('image')
                            ->label('Image')
                            ->state(fn (Product $record): ?string => CompanyMedia::publicUrl($record->image, $record))
                            ->height(120)
                            ->square(),

                        TextEntry::make('name')
                            ->label('Product Name'),

                        TextEntry::make('category.name')
                            ->label('Category')
                            ->placeholder('No category'),

                        TextEntry::make('sku')
                            ->label('SKU'),

                        TextEntry::make('barcode')
                            ->label('Barcode')
                            ->placeholder('No barcode'),

                        TextEntry::make('brand')
                            ->label('Brand')
                            ->placeholder('No brand'),

                        TextEntry::make('unit')
                            ->label('Unit'),

                        IconEntry::make('is_active')
                            ->label('Active')
                            ->boolean(),

                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => Product::STATUSES[$state] ?? Product::STATUSES[Product::STATUS_AVAILABLE])
                            ->color(fn (?string $state): string => $state === Product::STATUS_COMING_SOON ? 'warning' : 'success'),
                    ])
                    ->columns(2),

                Section::make('Pricing and Inventory')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('cost_price')
                            ->label('Cost Price')
                            ->formatStateUsing(fn ($state) => $state === null ? 'Not set' : 'BDT '.number_format((float) $state, 2)),

                        TextEntry::make('sale_price')
                            ->label('Sale Price')
                            ->formatStateUsing(fn ($state, $record) => 'BDT '.number_format((float) $record->selling_price, 2)),

                        TextEntry::make('stock')
                            ->label('Current Stock')
                            ->badge()
                            ->color(fn ($record): string => $record->isLowStock() ? 'danger' : 'success'),

                        TextEntry::make('reorder_level')
                            ->label('Reorder Level')
                            ->badge()
                            ->color('gray'),

                        TextEntry::make('vat_rate')
                            ->label('VAT Rate')
                            ->formatStateUsing(fn ($state) => number_format((float) $state, 2).'%'),
                    ])
                    ->columns(2),

                Section::make('Description')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('description')
                            ->label('Description')
                            ->placeholder('No description')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
