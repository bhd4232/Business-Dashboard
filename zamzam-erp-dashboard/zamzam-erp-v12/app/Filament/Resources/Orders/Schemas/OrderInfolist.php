<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Invoice')
                    ->schema([
                        TextEntry::make('order_number')->label('Invoice Number'),
                        TextEntry::make('customer.name')->label('Customer'),
                        TextEntry::make('order_date')->date(),
                        TextEntry::make('status')->badge(),
                    ])
                    ->columns(2),

                Section::make('Totals')
                    ->schema([
                        TextEntry::make('subtotal')->money('BDT'),
                        TextEntry::make('discount')->money('BDT'),
                        TextEntry::make('vat')->money('BDT'),
                        TextEntry::make('total_amount')->money('BDT'),
                        TextEntry::make('paid_amount')->money('BDT'),
                        TextEntry::make('due_amount')->money('BDT'),
                    ])
                    ->columns(3),

                Section::make('Items')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('')
                            ->schema([
                                TextEntry::make('product.name')
                                    ->label('Product'),

                                TextEntry::make('quantity')
                                    ->badge(),

                                TextEntry::make('unit_price')
                                    ->money('BDT'),

                                TextEntry::make('subtotal')
                                    ->money('BDT'),
                            ])
                            ->columns(4)
                            ->contained(false)
                            ->columnSpanFull(),
                    ]),

                TextEntry::make('note')->columnSpanFull(),
            ]);
    }
}
