<?php

namespace App\Filament\Resources\MetaAdAccounts\Pages;

use App\Filament\Concerns\HasStickyHeaderFormActions;
use App\Filament\Resources\MetaAdAccounts\MetaAdAccountResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMetaAdAccount extends CreateRecord
{
    use HasStickyHeaderFormActions;

    protected static string $resource = MetaAdAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->getStickySaveFormAction(),
        ];
    }
}
