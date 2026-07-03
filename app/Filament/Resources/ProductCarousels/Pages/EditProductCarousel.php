<?php

namespace App\Filament\Resources\ProductCarousels\Pages;

use App\Filament\Resources\ProductCarousels\ProductCarouselResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProductCarousel extends EditRecord
{
    protected static string $resource = ProductCarouselResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
