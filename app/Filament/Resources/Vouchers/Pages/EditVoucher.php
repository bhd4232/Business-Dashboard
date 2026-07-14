<?php

namespace App\Filament\Resources\Vouchers\Pages;

use App\Filament\Concerns\HasStickyHeaderFormActions;
use App\Filament\Resources\Vouchers\VoucherResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditVoucher extends EditRecord
{
    use HasStickyHeaderFormActions;

    protected static string $resource = VoucherResource::class;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make(), $this->getStickySaveFormAction()];
    }
}
