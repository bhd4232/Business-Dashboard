<?php

namespace App\Filament\Resources\ExpenseScans\Pages;

use App\Filament\Resources\ExpenseScans\ExpenseScanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListExpenseScans extends ListRecords
{
    protected static string $resource = ExpenseScanResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Scan a photo')->icon(Heroicon::OutlinedCamera)];
    }
}
