<?php

namespace App\Filament\Resources\SupplierPayments\Pages;

use App\Filament\Concerns\HasStickyHeaderFormActions;
use App\Filament\Resources\SupplierPayments\SupplierPaymentResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditSupplierPayment extends EditRecord
{
    use HasStickyHeaderFormActions;

    protected static string $resource = SupplierPaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make(), $this->getStickySaveFormAction()];
    }
}
