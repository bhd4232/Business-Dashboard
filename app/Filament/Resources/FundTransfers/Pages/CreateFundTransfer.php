<?php

namespace App\Filament\Resources\FundTransfers\Pages;

use App\Filament\Concerns\HasStickyHeaderFormActions;
use App\Filament\Resources\FundTransfers\FundTransferResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateFundTransfer extends CreateRecord
{
    use HasStickyHeaderFormActions;

    protected static string $resource = FundTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [$this->getStickySaveFormAction()];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['requested_by'] = Auth::id();

        return $data;
    }
}
