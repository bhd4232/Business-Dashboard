<?php

namespace App\Filament\Resources\Purchases\Schemas;

use App\Models\Purchase;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PurchaseInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Purchase')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('purchase_number')->label('Purchase Number'),
                        TextEntry::make('supplier.name')->label('Supplier'),
                        TextEntry::make('purchase_date')->date(),
                        TextEntry::make('status')->badge(),
                    ])
                    ->columns(2),

                Section::make('Document Tracking')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('lc_number')
                            ->label('LC Number')
                            ->placeholder('Not set'),
                        TextEntry::make('lc_date')
                            ->label('LC Date')
                            ->date()
                            ->placeholder('Not set'),
                        TextEntry::make('pi_number')
                            ->label('PI Number')
                            ->placeholder('Not set'),
                        TextEntry::make('pi_date')
                            ->label('PI Date')
                            ->date()
                            ->placeholder('Not set'),
                        TextEntry::make('ci_number')
                            ->label('CI Number')
                            ->placeholder('Not set'),
                        TextEntry::make('ci_date')
                            ->label('CI Date')
                            ->date()
                            ->placeholder('Not set'),
                    ])
                    ->columns(3),

                Section::make('China to BD Costs')
                    ->columnSpanFull()
                    ->schema(self::chinaToBdCostEntries())
                    ->columns(3),

                Section::make('Totals')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('subtotal')->moneyWithoutTrailingZeroes('BDT'),
                        TextEntry::make('china_to_bd_cost_total')
                            ->label('China to BD Costs')
                            ->state(fn (Purchase $record): float => $record->chinaToBdCostTotal())
                            ->moneyWithoutTrailingZeroes('BDT'),
                        TextEntry::make('landed_cost_total')
                            ->label('Landed Cost Total')
                            ->state(fn (Purchase $record): float => $record->landedCostTotal())
                            ->moneyWithoutTrailingZeroes('BDT'),
                        TextEntry::make('discount')->moneyWithoutTrailingZeroes('BDT'),
                        TextEntry::make('vat')->moneyWithoutTrailingZeroes('BDT'),
                        TextEntry::make('total_amount')->moneyWithoutTrailingZeroes('BDT'),
                        TextEntry::make('paid_amount')->moneyWithoutTrailingZeroes('BDT'),
                        TextEntry::make('due_amount')->moneyWithoutTrailingZeroes('BDT'),
                    ])
                    ->columns(3),

                Section::make('Items')
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('')
                            ->schema([
                                TextEntry::make('product.name')
                                    ->label('Product'),

                                TextEntry::make('quantity')
                                    ->badge(),

                                TextEntry::make('unit_cost')
                                    ->moneyWithoutTrailingZeroes('BDT'),

                                TextEntry::make('subtotal')
                                    ->moneyWithoutTrailingZeroes('BDT'),

                                TextEntry::make('allocated_cost')
                                    ->label('Allocated Cost')
                                    ->moneyWithoutTrailingZeroes('BDT'),

                                TextEntry::make('landed_unit_cost')
                                    ->label('Landed Unit Cost')
                                    ->moneyWithoutTrailingZeroes('BDT'),
                            ])
                            ->columns(6)
                            ->contained(false)
                            ->columnSpanFull(),
                    ]),

                TextEntry::make('note')->columnSpanFull(),
            ]);
    }

    protected static function chinaToBdCostEntries(): array
    {
        return [
            ...collect(Purchase::CHINA_TO_BD_COST_FIELDS)
                ->map(fn (string $label, string $field): TextEntry => TextEntry::make($field)
                    ->label($label)
                    ->moneyWithoutTrailingZeroes('BDT'))
                ->all(),
            RepeatableEntry::make('custom_costs')
                ->label('Custom Fields')
                ->schema([
                    TextEntry::make('label')
                        ->label('Field Name'),
                    TextEntry::make('amount')
                        ->label('Amount')
                        ->moneyWithoutTrailingZeroes('BDT'),
                ])
                ->columns(2)
                ->contained(false)
                ->columnSpanFull(),
        ];
    }
}
