<?php

namespace App\Filament\Resources\CustomerRiskProfiles\Pages;

use App\Filament\Resources\CustomerRiskProfiles\CustomerRiskProfileResource;
use Filament\Resources\Pages\ListRecords;

class ListCustomerRiskProfiles extends ListRecords
{
    protected static string $resource = CustomerRiskProfileResource::class;
}
