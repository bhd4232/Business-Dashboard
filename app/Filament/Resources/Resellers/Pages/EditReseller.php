<?php

namespace App\Filament\Resources\Resellers\Pages;

use App\Filament\Concerns\HasStickyHeaderFormActions;
use App\Filament\Resources\Resellers\ResellerResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditReseller extends EditRecord
{
    use HasStickyHeaderFormActions;

    protected static string $resource = ResellerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            $this->getStickySaveFormAction(),
        ];
    }
}
