<?php

namespace App\Filament\Resources\CourierProviders\Pages;

use App\Filament\Concerns\HasStickyHeaderFormActions;
use App\Filament\Resources\CourierProviders\CourierProviderResource;
use Filament\Resources\Pages\EditRecord;

class EditCourierProvider extends EditRecord
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
