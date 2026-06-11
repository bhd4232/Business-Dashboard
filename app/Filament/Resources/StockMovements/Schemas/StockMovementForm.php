<?php

namespace App\Filament\Resources\StockMovements\Schemas;

use App\Models\StockMovement;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class StockMovementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Movement Details')
                    ->schema([
                        Select::make('product_id')
                            ->label('Product')
                            ->relationship('product', 'name')
                            ->searchable()
                            ->required(),

                        Select::make('type')
                            ->label('Type')
                            ->options(StockMovement::TYPES)
                            ->live()
                            ->required(),

                        TextInput::make('quantity')
                            ->label('Quantity')
                            ->integer()
                            ->required()
                            ->minValue(fn (Get $get): ?int => $get('type') === 'adjustment' ? null : 1)
                            ->helperText(fn (Get $get): string => $get('type') === 'adjustment'
                                ? 'Use a signed non-zero value: positive adds stock, negative removes stock.'
                                : 'Use a positive quantity. Sales are automatically counted as outgoing stock.'),

                        TextInput::make('reference_type')
                            ->label('Reference Type')
                            ->maxLength(255),

                        TextInput::make('reference_id')
                            ->label('Reference ID')
                            ->numeric()
                            ->minValue(1),

                        Textarea::make('note')
                            ->label('Note')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}
