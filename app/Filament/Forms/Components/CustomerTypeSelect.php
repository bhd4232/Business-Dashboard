<?php

namespace App\Filament\Forms\Components;

use App\Models\Customer;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class CustomerTypeSelect
{
    public static function make(string $field = 'customer_type'): Select
    {
        return Select::make($field)
            ->label('Customer Type')
            ->options(fn (): array => Customer::typeOptions())
            ->getOptionLabelUsing(fn (?string $value): ?string => Customer::typeLabel($value))
            ->default('regular')
            ->required()
            ->searchable()
            ->preload()
            ->createOptionForm([
                TextInput::make('name')
                    ->label('Type Name')
                    ->placeholder('Example: Corporate, Dealer, Reseller')
                    ->required()
                    ->maxLength(50),
            ])
            ->createOptionUsing(fn (array $data): string => Customer::typeKey($data['name']))
            ->createOptionAction(fn (Action $action): Action => $action
                ->label('Add type')
                ->modalHeading('Add customer type')
                ->modalSubmitActionLabel('Add type'));
    }
}
