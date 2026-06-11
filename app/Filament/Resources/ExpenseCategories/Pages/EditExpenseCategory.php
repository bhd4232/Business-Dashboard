<?php

namespace App\Filament\Resources\ExpenseCategories\Pages;

use App\Filament\Concerns\HasStickyHeaderFormActions;
use App\Filament\Resources\ExpenseCategories\ExpenseCategoryResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditExpenseCategory extends EditRecord
{
    use HasStickyHeaderFormActions;

    protected static string $resource = ExpenseCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make(), $this->getStickySaveFormAction()];
    }
}
