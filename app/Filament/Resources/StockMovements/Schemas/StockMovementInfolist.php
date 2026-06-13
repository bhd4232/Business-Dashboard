<?php

namespace App\Filament\Resources\StockMovements\Schemas;

use App\Models\StockMovement;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StockMovementInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Movement Details')
                    ->schema([
                        TextEntry::make('product.name')
                            ->label('Product'),

                        TextEntry::make('type')
                            ->label('Type')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => StockMovement::TYPES[$state] ?? ucfirst($state)),

                        TextEntry::make('quantity')
                            ->label('Entered Quantity'),

                        TextEntry::make('signed_quantity')
                            ->label('Stock Impact')
                            ->badge()
                            ->color(fn (int $state): string => $state < 0 ? 'danger' : 'success'),

                        TextEntry::make('reason')
                            ->label('Adjustment Reason')
                            ->placeholder('No reason'),

                        TextEntry::make('reference_type')
                            ->label('Reference Type')
                            ->placeholder('No reference'),

                        TextEntry::make('reference_id')
                            ->label('Reference ID')
                            ->placeholder('No reference'),

                        TextEntry::make('note')
                            ->label('Note')
                            ->placeholder('No note')
                            ->columnSpanFull(),

                        TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime(),
                    ])
                    ->columns(2),
            ]);
    }
}
