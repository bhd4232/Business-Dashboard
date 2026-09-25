<?php

namespace App\Filament\Resources\CustomerPayments\Schemas;

use App\Models\Account;
use App\Models\CustomerPayment;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class CustomerPaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Payment')
                ->columnSpanFull()
                ->schema([
                    TextInput::make('payment_number')
                        ->label('Payment Number')
                        ->default(fn (): string => CustomerPayment::nextPaymentNumber())
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),
                    Select::make('customer_id')
                        ->label('Customer')
                        ->relationship('customer', 'name', fn ($query) => $query->where('is_active', true))
                        ->searchable()
                        ->required(),
                    // The payment method is the account the money came into
                    // (Cash, bKash, bank… from the Accounts page); the generic
                    // method is filled in from that account's type.
                    Select::make('account_id')
                        ->label(__('Payment Method'))
                        ->relationship('account', 'name', fn ($query) => $query->manual()->where('is_active', true))
                        ->searchable()
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn ($state, Set $set) => $set('method', self::methodForAccount($state)))
                        ->helperText(__('The account the payment was received into. Its balance updates automatically.')),
                    DatePicker::make('payment_date')->default(now())->required(),
                    TextInput::make('amount')->numeric()->prefix('৳')->minValue(0.01)->required(),
                    Hidden::make('method')->default('cash'),
                    TextInput::make('reference')->maxLength(255),
                ])->columns(2),
            Section::make('Note')->columnSpanFull()->schema([
                Textarea::make('note')->rows(3)->columnSpanFull(),
            ]),
        ]);
    }

    protected static function methodForAccount(mixed $accountId): string
    {
        $type = filled($accountId) ? Account::query()->whereKey($accountId)->value('type') : null;

        return array_key_exists((string) $type, CustomerPayment::METHODS) ? $type : 'other';
    }
}
