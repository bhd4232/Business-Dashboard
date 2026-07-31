<?php

namespace App\Filament\Resources\Accounts;

use App\Filament\Clusters\Finance;
use App\Filament\Resources\Accounts\Pages\CreateAccount;
use App\Filament\Resources\Accounts\Pages\EditAccount;
use App\Filament\Resources\Accounts\Pages\ListAccounts;
use App\Filament\Resources\Accounts\Pages\ViewAccount;
use App\Filament\Resources\Accounts\RelationManagers\LedgersRelationManager;
use App\Filament\Resources\Accounts\Schemas\AccountForm;
use App\Filament\Resources\Accounts\Schemas\AccountInfolist;
use App\Filament\Resources\Accounts\Tables\AccountsTable;
use App\Models\Account;
use App\Services\CompanyContext;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AccountResource extends Resource
{
    protected static ?string $model = Account::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWallet;

    protected static ?string $cluster = Finance::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return AccountForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AccountInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AccountsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (! app(CompanyContext::class)->isAllCompanies()) {
            return $query;
        }

        $representativeSystemAccountIds = Account::withoutGlobalScopes()
            ->selectRaw('MIN(accounts.id)')
            ->whereNotNull('accounts.system_key')
            ->groupBy('accounts.system_key');

        return $query->where(function (Builder $query) use ($representativeSystemAccountIds): void {
            $query
                ->whereNull('accounts.system_key')
                ->orWhereIn('accounts.id', $representativeSystemAccountIds);
        });
    }

    public static function canEdit(Model $record): bool
    {
        return $record instanceof Account
            && ! $record->isSystem()
            && (Auth::user()?->canEditAccounts() ?? false);
    }

    public static function canDelete(Model $record): bool
    {
        return $record instanceof Account
            && ! $record->isSystem()
            && (Auth::user()?->canDeleteSensitiveRecords() ?? false);
    }

    public static function canDeleteAny(): bool
    {
        return Auth::user()?->canDeleteSensitiveRecords() ?? false;
    }

    public static function getRelations(): array
    {
        return [
            LedgersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAccounts::route('/'),
            'create' => CreateAccount::route('/create'),
            'view' => ViewAccount::route('/{record}'),
            'edit' => EditAccount::route('/{record}/edit'),
        ];
    }
}
