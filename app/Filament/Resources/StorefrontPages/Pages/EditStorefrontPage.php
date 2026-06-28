<?php

namespace App\Filament\Resources\StorefrontPages\Pages;

use App\Filament\Resources\StorefrontPages\StorefrontPageResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStorefrontPage extends EditRecord
{
    protected static string $resource = StorefrontPageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
