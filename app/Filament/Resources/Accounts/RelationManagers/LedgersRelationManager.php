<?php

namespace App\Filament\Resources\Accounts\RelationManagers;

use App\Models\TransactionLedger;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LedgersRelationManager extends RelationManager
{
    protected static string $relationship = 'ledgers';

    protected static ?string $title = 'Transaction History';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('transaction_date')->date()->sortable(),
                TextColumn::make('type')->badge(),
                TextColumn::make('direction')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'in' ? 'success' : 'danger'),
                TextColumn::make('amount')->money('BDT')->sortable(),
                TextColumn::make('note')->limit(50)->placeholder('-'),
                TextColumn::make('reference_type')->label('Reference')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('reference_id')->label('Reference ID')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')->options(TransactionLedger::TYPES),
                SelectFilter::make('direction')->options(TransactionLedger::DIRECTIONS),
            ])
            ->recordActions([ViewAction::make()])
            ->defaultSort('transaction_date', 'desc');
    }
}
