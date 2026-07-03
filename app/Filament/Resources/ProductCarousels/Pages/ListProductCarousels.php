<?php

namespace App\Filament\Resources\ProductCarousels\Pages;

use App\Filament\Resources\ProductCarousels\ProductCarouselResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProductCarousels extends ListRecords
{
    protected static string $resource = ProductCarouselResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
