<?php

namespace App\Filament\Resources\Expenses\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ExpenseInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Expense')->schema([
                TextEntry::make('expense_number')->label('Expense Number'),
                TextEntry::make('category.name')->label('Category'),
                TextEntry::make('account.name')->label('Account'),
                TextEntry::make('expense_date')->date(),
                TextEntry::make('amount')->money('BDT'),
                TextEntry::make('reference'),
            ])->columns(2),
            TextEntry::make('note')->columnSpanFull(),
        ]);
    }
}
