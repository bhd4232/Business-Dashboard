<?php

namespace App\Filament\Resources\CustomerPayments\Schemas;

use App\Models\CustomerPayment;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerPaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Payment')
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
                    Select::make('account_id')
                        ->label('Receive To Account')
                        ->relationship('account', 'name', fn ($query) => $query->where('is_active', true))
                        ->searchable()
                        ->required(),
                    DatePicker::make('payment_date')->default(now())->required(),
                    TextInput::make('amount')->numeric()->prefix('BDT')->minValue(0.01)->required(),
                    Select::make('method')->options(CustomerPayment::METHODS)->default('cash')->required(),
                    TextInput::make('reference')->maxLength(255),
                ])->columns(2),
            Section::make('Note')->schema([
                Textarea::make('note')->rows(3)->columnSpanFull(),
            ]),
        ]);
    }
}
