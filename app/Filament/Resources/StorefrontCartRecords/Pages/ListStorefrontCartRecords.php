<?php

namespace App\Filament\Resources\StorefrontCartRecords\Pages;

use App\Filament\Resources\StorefrontCartRecords\StorefrontCartRecordResource;
use App\Filament\Resources\StorefrontCartRecords\Widgets\StorefrontRecoveryOverview;
use Filament\Resources\Pages\ListRecords;

class ListStorefrontCartRecords extends ListRecords
{
    protected static string $resource = StorefrontCartRecordResource::class;

    protected function getHeaderWidgets(): array
    {
        return [StorefrontRecoveryOverview::class];
    }
}
