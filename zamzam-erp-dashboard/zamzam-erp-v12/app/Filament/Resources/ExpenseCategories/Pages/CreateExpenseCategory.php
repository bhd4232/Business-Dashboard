<?php

namespace App\Filament\Resources\ExpenseCategories\Pages;

use App\Filament\Concerns\HasStickyHeaderFormActions;
use App\Filament\Resources\ExpenseCategories\ExpenseCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateExpenseCategory extends CreateRecord
{
    use HasStickyHeaderFormActions;

    protected static string $resource = ExpenseCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [$this->getStickySaveFormAction()];
    }
}
