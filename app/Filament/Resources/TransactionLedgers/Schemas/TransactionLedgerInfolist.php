<?php

namespace App\Filament\Resources\TransactionLedgers\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TransactionLedgerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Transaction')->columnSpanFull()->schema([
                TextEntry::make('account.name')->label('Account'),
                TextEntry::make('type')->badge(),
                TextEntry::make('direction')->badge(),
                TextEntry::make('amount')->money('BDT'),
                TextEntry::make('transaction_date')->date(),
                TextEntry::make('reference_type')->label('Reference Type'),
                TextEntry::make('reference_id')->label('Reference ID'),
                TextEntry::make('note')->columnSpanFull(),
            ])->columns(2),
        ]);
    }
}
