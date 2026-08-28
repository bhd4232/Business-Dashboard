<?php

namespace App\Filament\Resources\StorefrontPaymentMethods\Pages;

use App\Filament\Resources\StorefrontPaymentMethods\StorefrontPaymentMethodResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStorefrontPaymentMethod extends EditRecord
{
    protected static string $resource = StorefrontPaymentMethodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
