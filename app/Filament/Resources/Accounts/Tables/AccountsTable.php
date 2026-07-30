<?php

namespace App\Filament\Resources\Accounts\Tables;

use App\Models\Account;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class AccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Account::TYPES[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('balance')
                    ->label('Balance')
                    ->state(fn (Account $record): float => $record->balance())
                    ->money('BDT'),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->filters([
                SelectFilter::make('type')->options(Account::TYPES),
                TernaryFilter::make('is_active')->label('Active status'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->visible(fn (Account $record): bool => ! $record->isSystem()),
            ])
            ->checkIfRecordIsSelectableUsing(fn (Account $record): bool => ! $record->isSystem())
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
