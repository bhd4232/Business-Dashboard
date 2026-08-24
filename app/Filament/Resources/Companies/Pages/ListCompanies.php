<?php

namespace App\Filament\Resources\Companies\Pages;

use App\Filament\Resources\Companies\CompanyResource;
use App\Services\CompanyContext;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListCompanies extends ListRecords
{
    protected static string $resource = CompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();
        $companyContext = app(CompanyContext::class);

        if ($companyContext->isAllCompanies()) {
            return $query;
        }

        if ($companyContext->hasCompany()) {
            return $query->whereKey($companyContext->id());
        }

        return $query->whereRaw('1 = 0');
    }
}
