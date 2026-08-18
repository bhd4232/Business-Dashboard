<?php

namespace App\Filament\Resources\StorefrontSettings\Pages;

use App\Filament\Resources\StorefrontSettings\StorefrontSettingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStorefrontSettings extends ListRecords
{
    protected static string $resource = StorefrontSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
