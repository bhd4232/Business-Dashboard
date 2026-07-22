<?php

namespace App\Filament\Resources\ConversationChannels\Pages;

use App\Filament\Resources\ConversationChannels\ConversationChannelResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditConversationChannel extends EditRecord
{
    protected static string $resource = ConversationChannelResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['access_token'] = null;
        $data['app_secret'] = null;
        $data['verify_token'] = null;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        foreach (['access_token', 'app_secret', 'verify_token'] as $secret) {
            if (blank($data[$secret] ?? null)) {
                unset($data[$secret]);
            }
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
