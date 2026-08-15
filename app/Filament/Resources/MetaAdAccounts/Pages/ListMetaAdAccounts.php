<?php

namespace App\Filament\Resources\MetaAdAccounts\Pages;

use App\Filament\Resources\MetaAdAccounts\MetaAdAccountResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMetaAdAccounts extends ListRecords
{
    protected static string $resource = MetaAdAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
