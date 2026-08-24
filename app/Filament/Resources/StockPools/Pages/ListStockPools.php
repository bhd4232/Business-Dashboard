<?php

namespace App\Filament\Resources\StockPools\Pages;

use App\Filament\Resources\StockPools\StockPoolResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStockPools extends ListRecords
{
    protected static string $resource = StockPoolResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Link products'),
        ];
    }
}
