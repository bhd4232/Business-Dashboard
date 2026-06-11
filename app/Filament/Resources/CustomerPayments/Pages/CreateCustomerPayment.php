<?php

namespace App\Filament\Resources\CustomerPayments\Pages;

use App\Filament\Concerns\HasStickyHeaderFormActions;
use App\Filament\Resources\CustomerPayments\CustomerPaymentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomerPayment extends CreateRecord
{
    use HasStickyHeaderFormActions;

    protected static string $resource = CustomerPaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [$this->getStickySaveFormAction()];
    }
}
