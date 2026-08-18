<?php

namespace App\Filament\Concerns;

use Filament\Actions\Action;

trait HasStickyHeaderFormActions
{
    protected function getStickySaveFormAction(): Action
    {
        $hasFormWrapper = $this->hasFormWrapper();
        $submitMethod = $this->getSubmitFormLivewireMethodName();

        return Action::make('saveChanges')
            ->label('Save changes')
            ->submit($hasFormWrapper ? $submitMethod : null)
            ->formId($hasFormWrapper ? 'form' : null)
            ->action($hasFormWrapper ? null : $submitMethod)
            ->keyBindings(['mod+s']);
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCancelFormAction(),
        ];
    }
}
