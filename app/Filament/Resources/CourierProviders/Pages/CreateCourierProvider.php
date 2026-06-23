<?php

namespace App\Filament\Resources\CourierProviders\Pages;

use App\Filament\Concerns\HasStickyHeaderFormActions;
use App\Filament\Resources\CourierProviders\CourierProviderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCourierProvider extends CreateRecord
{
    use HasStickyHeaderFormActions;

    protected static string $resource = CourierProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->getStickySaveFormAction(),
        ];
    }
}
