<?php

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Customer')
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('phone'),
                        TextEntry::make('email'),
                        TextEntry::make('customer_type')
                            ->label('Customer Type')
                            ->badge(),
                        TextEntry::make('customer_source')
                            ->label('Customer Source')
                            ->badge()
                            ->placeholder('Not set'),
                        IconEntry::make('is_active')->boolean(),
                    ])
                    ->columns(2),

                Section::make('Balance')
                    ->schema([
                        TextEntry::make('opening_balance')->money('BDT'),
                        TextEntry::make('current_balance')->money('BDT'),
                    ])
                    ->columns(2),

                TextEntry::make('address')->columnSpanFull(),
            ]);
    }
}
