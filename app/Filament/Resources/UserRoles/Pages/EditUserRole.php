<?php

namespace App\Filament\Resources\UserRoles\Pages;

use App\Filament\Concerns\HasStickyHeaderFormActions;
use App\Filament\Resources\UserRoles\UserRoleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUserRole extends EditRecord
{
    use HasStickyHeaderFormActions;

    protected static string $resource = UserRoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->getStickySaveFormAction(),
            DeleteAction::make(),
        ];
    }
}
