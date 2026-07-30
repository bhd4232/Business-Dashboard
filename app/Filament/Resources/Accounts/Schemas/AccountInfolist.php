<?php

namespace App\Filament\Resources\Accounts\Schemas;

use App\Models\Account;
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
                    TextEntry::make('type')
                        ->badge()
                        ->formatStateUsing(fn (string $state): string => Account::TYPES[$state] ?? $state),
                    IconEntry::make('is_active')->boolean(),
                ])->columns(2),
            Section::make('Balance')
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('balance')
                        ->label('Balance')
                        ->state(fn (Account $record): float => $record->balance())
                        ->money('BDT'),
                ]),
        ]);
    }
}
