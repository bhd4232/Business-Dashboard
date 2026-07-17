<?php

namespace App\Filament\Resources\Accounts\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AccountInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Account')
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('name'),
                    TextEntry::make('type')->badge(),
                    IconEntry::make('is_active')->boolean(),
                ])->columns(2),
            Section::make('Balance')
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('opening_balance')->money('BDT'),
                    TextEntry::make('current_balance')->money('BDT'),
                ])->columns(2),
        ]);
    }
}
