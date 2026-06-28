<?php

namespace App\Filament\Resources\CustomerBlacklists\Pages;

use App\Filament\Resources\CustomerBlacklists\CustomerBlacklistResource;
use Filament\Resources\Pages\EditRecord;

class EditCustomerBlacklist extends EditRecord
{
    protected static string $resource = CustomerBlacklistResource::class;
}
