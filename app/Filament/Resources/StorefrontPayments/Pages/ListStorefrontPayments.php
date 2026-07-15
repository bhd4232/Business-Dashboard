<?php

namespace App\Filament\Resources\StorefrontPayments\Pages;

use App\Filament\Resources\StorefrontPayments\StorefrontPaymentResource;
use Filament\Resources\Pages\ListRecords;

class ListStorefrontPayments extends ListRecords
{
    protected static string $resource = StorefrontPaymentResource::class;
}
