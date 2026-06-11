<?php

namespace App\Filament\Resources\TransactionLedgers\Tables;

use App\Models\TransactionLedger;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TransactionLedgersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('account'))
            ->columns([
                TextColumn::make('transaction_date')->date()->sortable(),
                TextColumn::make('account.name')->label('Account')->searchable()->sortable(),
                TextColumn::make('type')->badge(),
                TextColumn::make('direction')->badge()->color(fn (string $state): string => $state === 'in' ? 'success' : 'danger'),
                TextColumn::make('amount')->money('BDT')->sortable(),
                TextColumn::make('reference_type')->label('Reference')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('reference_id')->label('Reference ID')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('account_id')->label('Account')->relationship('account', 'name')->searchable(),
                SelectFilter::make('type')->options(TransactionLedger::TYPES),
                SelectFilter::make('direction')->options(TransactionLedger::DIRECTIONS),
            ])
            ->recordActions([ViewAction::make()])
            ->defaultSort('transaction_date', 'desc');
    }
}
