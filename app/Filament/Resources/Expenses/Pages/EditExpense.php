<?php

namespace App\Filament\Resources\Expenses\Pages;

use App\Filament\Concerns\HasStickyHeaderFormActions;
use App\Filament\Resources\Expenses\ExpenseResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditExpense extends EditRecord
{
    use HasStickyHeaderFormActions;

    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make(), $this->getStickySaveFormAction()];
    }
}
