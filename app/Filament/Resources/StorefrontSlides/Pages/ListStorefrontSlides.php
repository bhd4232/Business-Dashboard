<?php

namespace App\Filament\Resources\StorefrontSlides\Pages;

use App\Filament\Resources\StorefrontSlides\StorefrontSlideResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStorefrontSlides extends ListRecords
{
    protected static string $resource = StorefrontSlideResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
