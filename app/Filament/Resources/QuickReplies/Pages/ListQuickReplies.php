<?php

namespace App\Filament\Resources\QuickReplies\Pages;

use App\Filament\Resources\QuickReplies\QuickReplyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListQuickReplies extends ListRecords
{
    protected static string $resource = QuickReplyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
