<?php

namespace App\Filament\Resources\CustomerBlacklists\Pages;

use App\Filament\Resources\CustomerBlacklists\CustomerBlacklistResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomerBlacklist extends CreateRecord
{
    protected static string $resource = CustomerBlacklistResource::class;
}
