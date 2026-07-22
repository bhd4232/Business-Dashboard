<?php

namespace App\Filament\Resources\TransactionLedgers;

use App\Filament\Clusters\Accounts;
use App\Filament\Resources\TransactionLedgers\Pages\ListTransactionLedgers;
use App\Filament\Resources\TransactionLedgers\Pages\ViewTransactionLedger;
use App\Filament\Resources\TransactionLedgers\Schemas\TransactionLedgerInfolist;
use App\Filament\Resources\TransactionLedgers\Tables\TransactionLedgersTable;
use App\Models\TransactionLedger;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TransactionLedgerResource extends Resource
{
    protected static ?string $model = TransactionLedger::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $cluster = Accounts::class;

    protected static ?string $recordTitleAttribute = 'type';

    protected static bool $shouldRegisterNavigation = false;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function infolist(Schema $schema): Schema
    {
        return TransactionLedgerInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TransactionLedgersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTransactionLedgers::route('/'),
            'view' => ViewTransactionLedger::route('/{record}'),
        ];
    }
}
