<?php

namespace App\Filament\Resources\SupplierPayments\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SupplierPaymentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Payment')->columnSpanFull()->schema([
                TextEntry::make('payment_number')->label('Payment Number'),
                TextEntry::make('supplier.name')->label('Supplier'),
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
