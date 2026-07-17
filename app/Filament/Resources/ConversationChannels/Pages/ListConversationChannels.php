<?php

namespace App\Filament\Resources\ConversationChannels\Pages;

use App\Filament\Resources\ConversationChannels\ConversationChannelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListConversationChannels extends ListRecords
{
    protected static string $resource = ConversationChannelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
