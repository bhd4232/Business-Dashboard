<?php

namespace App\Filament\Resources\ExpenseCategories\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ExpenseCategoryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Category')->schema([
                TextEntry::make('name'),
                TextEntry::make('slug'),
                IconEntry::make('is_active')->boolean(),
                TextEntry::make('description')->columnSpanFull(),
            ])->columns(2),
        ]);
    }
}
