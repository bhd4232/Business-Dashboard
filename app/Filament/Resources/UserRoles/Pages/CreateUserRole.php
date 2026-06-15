<?php

namespace App\Filament\Resources\UserRoles\Pages;

use App\Filament\Concerns\HasStickyHeaderFormActions;
use App\Filament\Resources\UserRoles\UserRoleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUserRole extends CreateRecord
{
    use HasStickyHeaderFormActions;

    protected static string $resource = UserRoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->getStickySaveFormAction(),
        ];
    }
}
