<?php

namespace App\Filament\Resources\UserRoles\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\UserRoles\UserRoleResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListUserRoles extends ListRecords
{
    protected static string $resource = UserRoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToUsers')
                ->label('Back to Users')
                ->icon(Heroicon::OutlinedUserGroup)
                ->color('gray')
                ->url(fn (): string => UserResource::getUrl('index')),
            CreateAction::make(),
        ];
    }
}
