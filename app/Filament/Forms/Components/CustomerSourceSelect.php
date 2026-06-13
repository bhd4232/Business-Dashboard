<?php

namespace App\Filament\Forms\Components;

use App\Models\Customer;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class CustomerSourceSelect
{
    public static function make(string $field = 'customer_source'): Select
    {
        return Select::make($field)
            ->label('Customer Source')
            ->options(fn (): array => Customer::sourceOptions())
            ->getOptionLabelUsing(fn (?string $value): ?string => Customer::sourceLabel($value))
            ->searchable()
            ->preload()
            ->createOptionForm([
                TextInput::make('name')
                    ->label('Source Name')
                    ->placeholder('Example: TikTok, WhatsApp, Trade Fair')
                    ->required()
                    ->maxLength(50),
            ])
            ->createOptionUsing(fn (array $data): string => Customer::sourceKey($data['name']))
            ->createOptionAction(fn (Action $action): Action => $action
                ->label('Add source')
                ->modalHeading('Add customer source')
                ->modalSubmitActionLabel('Add source'));
    }
}
