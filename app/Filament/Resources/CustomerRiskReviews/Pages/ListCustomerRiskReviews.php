<?php

namespace App\Filament\Resources\CustomerRiskReviews\Pages;

use App\Filament\Resources\CustomerRiskReviews\CustomerRiskReviewResource;
use Filament\Resources\Pages\ListRecords;

class ListCustomerRiskReviews extends ListRecords
{
    protected static string $resource = CustomerRiskReviewResource::class;
}
