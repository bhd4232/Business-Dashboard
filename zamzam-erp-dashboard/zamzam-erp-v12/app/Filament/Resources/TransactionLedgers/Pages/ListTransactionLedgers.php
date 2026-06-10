<?php

namespace App\Filament\Resources\TransactionLedgers\Pages;

use App\Filament\Resources\TransactionLedgers\TransactionLedgerResource;
use Filament\Resources\Pages\ListRecords;

class ListTransactionLedgers extends ListRecords
{
    protected static string $resource = TransactionLedgerResource::class;
}
