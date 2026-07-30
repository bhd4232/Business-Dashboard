<?php

namespace App\Filament\Resources\InvestmentProjects\Pages;

use App\Filament\Resources\InvestmentProjects\InvestmentProjectResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInvestmentProjects extends ListRecords
{
    protected static string $resource = InvestmentProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
