<?php

namespace App\Filament\Resources\InvestmentProjects\Pages;

use App\Filament\Resources\InvestmentProjects\InvestmentProjectResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditInvestmentProject extends EditRecord
{
    protected static string $resource = InvestmentProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make()];
    }
}
