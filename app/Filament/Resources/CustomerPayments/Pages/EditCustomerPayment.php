<?php

namespace App\Filament\Resources\CustomerPayments\Pages;

use App\Filament\Concerns\HasStickyHeaderFormActions;
use App\Filament\Resources\CustomerPayments\CustomerPaymentResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCustomerPayment extends EditRecord
{
    use HasStickyHeaderFormActions;

    protected static string $resource = CustomerPaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make(), $this->getStickySaveFormAction()];
    }
}
