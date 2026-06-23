<?php

namespace App\Filament\Resources\CourierProviders\Pages;

use App\Filament\Resources\CourierProviders\CourierProviderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCourierProviders extends ListRecords
{
    protected static string $resource = CourierProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
