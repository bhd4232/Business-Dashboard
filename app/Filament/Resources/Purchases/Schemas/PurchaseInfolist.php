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
            ->components([
                Section::make('Purchase')
                    ->schema([
                        TextEntry::make('purchase_number')->label('Purchase Number'),
                        TextEntry::make('supplier.name')->label('Supplier'),
                        TextEntry::make('purchase_date')->date(),
                        TextEntry::make('status')->badge(),
                    ])
                    ->columns(2),

                Section::make('Document Tracking')
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
                    ->schema(self::chinaToBdCostEntries())
                    ->columns(3),

                Section::make('Totals')
                    ->schema([
                        TextEntry::make('subtotal')->money('BDT'),
                        TextEntry::make('china_to_bd_cost_total')
                            ->label('China to BD Costs')
                            ->state(fn (Purchase $record): float => $record->chinaToBdCostTotal())
                            ->money('BDT'),
                        TextEntry::make('landed_cost_total')
                            ->label('Landed Cost Total')
                            ->state(fn (Purchase $record): float => $record->landedCostTotal())
                            ->money('BDT'),
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

                                TextEntry::make('unit_cost')
                                    ->money('BDT'),

                                TextEntry::make('subtotal')
                                    ->money('BDT'),

                                TextEntry::make('allocated_cost')
                                    ->label('Allocated Cost')
                                    ->money('BDT'),

                                TextEntry::make('landed_unit_cost')
                                    ->label('Landed Unit Cost')
                                    ->money('BDT'),
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
                ->money('BDT'))
            ->all(),
            RepeatableEntry::make('custom_costs')
                ->label('Custom Fields')
                ->schema([
                    TextEntry::make('label')
                        ->label('Field Name'),
                    TextEntry::make('amount')
                        ->label('Amount')
                        ->money('BDT'),
                ])
                ->columns(2)
                ->contained(false)
                ->columnSpanFull(),
        ];
    }
}
