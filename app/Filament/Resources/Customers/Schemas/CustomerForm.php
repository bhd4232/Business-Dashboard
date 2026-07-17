<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Filament\Forms\Components\CustomerSourceSelect;
use App\Filament\Forms\Components\CustomerTypeSelect;
use App\Filament\Forms\Components\EmailInput;
use App\Filament\Forms\Components\PhoneInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Customer Information')
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        PhoneInput::make(),

                        EmailInput::make(),

                        CustomerTypeSelect::make(),

                        CustomerSourceSelect::make(),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])
                    ->columns(2),

                Section::make('Reseller')
                    ->columnSpanFull()
                    ->description('Storefront reseller applications land here as "Application pending". Approve to mark this customer as a wholesale reseller.')
                    ->schema([
                        \Filament\Forms\Components\Select::make('reseller_status')
                            ->label('Reseller status')
                            ->options(\App\Models\Customer::RESELLER_STATUSES)
                            ->default('none')
                            ->required(),
                        TextInput::make('business_name')
                            ->label('Business / shop name')
                            ->maxLength(255),
                        Textarea::make('reseller_note')
                            ->label('Application note')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make('Balance')
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('opening_balance')
                            ->numeric()
                            ->prefix('BDT')
                            ->default(0)
                            ->required(),

                        TextInput::make('current_balance')
                            ->numeric()
                            ->prefix('BDT')
                            ->default(0)
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Current balance is updated from confirmed and completed invoices.'),
                    ])
                    ->columns(2),

                Section::make('Address')
                    ->columnSpanFull()
                    ->schema([
                        Textarea::make('address')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
