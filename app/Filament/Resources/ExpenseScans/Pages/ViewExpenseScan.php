<?php

namespace App\Filament\Resources\ExpenseScans\Pages;

use App\Filament\Resources\ExpenseScans\ExpenseScanResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewExpenseScan extends ViewRecord
{
    protected static string $resource = ExpenseScanResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()->label('Review')];
    }

    /** Polled by the photo panel while the AI is still reading. */
    public function refreshScan(): void
    {
        $this->record->refresh();
    }
}
