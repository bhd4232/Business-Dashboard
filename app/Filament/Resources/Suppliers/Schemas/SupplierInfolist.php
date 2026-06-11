<?php

namespace App\Filament\Resources\Suppliers\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SupplierInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Supplier')
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('company_name')->label('Company'),
                        TextEntry::make('phone'),
                        TextEntry::make('email'),
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
