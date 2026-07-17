<?php

namespace App\Filament\Resources\CompanyFaqs\Pages;

use App\Filament\Resources\CompanyFaqs\CompanyFaqResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCompanyFaq extends EditRecord
{
    protected static string $resource = CompanyFaqResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
