<?php

namespace App\Filament\Resources\StorefrontMetaEvents\Pages;

use App\Filament\Pages\Integrations;
use App\Filament\Resources\StorefrontMetaEvents\StorefrontMetaEventResource;
use Filament\Resources\Pages\ListRecords;

class ListStorefrontMetaEvents extends ListRecords
{
    protected static string $resource = StorefrontMetaEventResource::class;

    public function mount(): void
    {
        $this->redirect(Integrations::getUrl(), navigate: true);
    }
}
