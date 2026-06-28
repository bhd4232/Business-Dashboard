<?php

namespace App\Filament\Resources\CustomerRiskEvents\Pages;

use App\Filament\Resources\CustomerRiskEvents\CustomerRiskEventResource;
use Filament\Resources\Pages\ListRecords;

class ListCustomerRiskEvents extends ListRecords
{
    protected static string $resource = CustomerRiskEventResource::class;
}
