<?php

namespace App\Filament\Concerns;

use Filament\Actions\Action;

trait HasStickyHeaderFormActions
{
    protected function getStickySaveFormAction(): Action
    {
        return Action::make('saveChanges')
            ->label('Save changes')
            ->action($this->getSubmitFormLivewireMethodName())
            ->keyBindings(['mod+s']);
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCancelFormAction(),
        ];
    }
}
