<?php

namespace App\Filament\Resources\FundSources\Pages;

use App\Filament\Concerns\HasStickyHeaderFormActions;
use App\Filament\Resources\FundSources\FundSourceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFundSource extends CreateRecord
{
    use HasStickyHeaderFormActions;

    protected static string $resource = FundSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [$this->getStickySaveFormAction()];
    }
}
