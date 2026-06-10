<?php

namespace App\Filament\Resources\SupplierPayments\Pages;

use App\Filament\Concerns\HasStickyHeaderFormActions;
use App\Filament\Resources\SupplierPayments\SupplierPaymentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSupplierPayment extends CreateRecord
{
    use HasStickyHeaderFormActions;

    protected static string $resource = SupplierPaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [$this->getStickySaveFormAction()];
    }
}
