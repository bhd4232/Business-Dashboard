<?php

namespace App\Filament\Resources\CustomerBlacklists\Pages;

use App\Filament\Resources\CustomerBlacklists\CustomerBlacklistResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCustomerBlacklists extends ListRecords
{
    protected static string $resource = CustomerBlacklistResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
