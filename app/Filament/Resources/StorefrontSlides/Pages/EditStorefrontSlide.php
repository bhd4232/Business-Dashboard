<?php

namespace App\Filament\Resources\StorefrontSlides\Pages;

use App\Filament\Resources\StorefrontSlides\StorefrontSlideResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStorefrontSlide extends EditRecord
{
    protected static string $resource = StorefrontSlideResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
