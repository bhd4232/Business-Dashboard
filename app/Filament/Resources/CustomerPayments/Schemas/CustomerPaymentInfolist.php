<?php

namespace App\Filament\Resources\CustomerPayments\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerPaymentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Payment')->schema([
                TextEntry::make('payment_number')->label('Payment Number'),
                TextEntry::make('customer.name')->label('Customer'),
                TextEntry::make('account.name')->label('Account'),
                TextEntry::make('payment_date')->date(),
                TextEntry::make('amount')->money('BDT'),
                TextEntry::make('method')->badge(),
                TextEntry::make('reference'),
            ])->columns(2),
            TextEntry::make('note')->columnSpanFull(),
        ]);
    }
}
