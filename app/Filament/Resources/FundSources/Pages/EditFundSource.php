<?php

namespace App\Filament\Resources\FundSources\Pages;

use App\Filament\Concerns\HasStickyHeaderFormActions;
use App\Filament\Resources\FundSources\FundSourceResource;
use Filament\Resources\Pages\EditRecord;

class EditFundSource extends EditRecord
{
    use HasStickyHeaderFormActions;

    protected static string $resource = FundSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [$this->getStickySaveFormAction()];
    }
}
