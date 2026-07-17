<?php

namespace App\Filament\Resources\ConversationChannels\Pages;

use App\Filament\Resources\ConversationChannels\ConversationChannelResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditConversationChannel extends EditRecord
{
    protected static string $resource = ConversationChannelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
