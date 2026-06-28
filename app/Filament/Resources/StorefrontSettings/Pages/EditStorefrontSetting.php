<?php

namespace App\Filament\Resources\StorefrontSettings\Pages;

use App\Filament\Resources\StorefrontPages\StorefrontPageResource;
use App\Filament\Resources\StorefrontSettings\StorefrontSettingResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditStorefrontSetting extends EditRecord
{
    protected static string $resource = StorefrontSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('managePages')
                ->label('Manage Pages')
                ->icon('heroicon-o-document-text')
                ->url(StorefrontPageResource::getUrl('index')),
            Action::make('createPage')
                ->label('New Page')
                ->icon('heroicon-o-plus')
                ->url(StorefrontPageResource::getUrl('create')),
        ];
    }
}
