<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\UserRoles\UserRoleResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('manageRoles')
                ->label('Manage Roles')
                ->icon(Heroicon::OutlinedShieldCheck)
                ->color('gray')
                ->url(fn (): string => UserRoleResource::getUrl('index'))
                ->visible(fn (): bool => UserRoleResource::canAccess()),
            CreateAction::make(),
        ];
    }
}
