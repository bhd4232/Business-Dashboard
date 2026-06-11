<?php

namespace App\Filament\Resources\Expenses\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExpensesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['category', 'account']))
            ->columns([
                TextColumn::make('expense_number')->label('Expense Number')->searchable()->sortable(),
                TextColumn::make('category.name')->label('Category')->searchable()->sortable(),
                TextColumn::make('account.name')->label('Account')->searchable()->sortable(),
                TextColumn::make('expense_date')->date()->sortable(),
                TextColumn::make('amount')->money('BDT')->sortable(),
            ])
            ->filters([
                SelectFilter::make('expense_category_id')->label('Category')->relationship('category', 'name')->searchable(),
                SelectFilter::make('account_id')->label('Account')->relationship('account', 'name')->searchable(),
            ])
            ->recordActions([ViewAction::make(), EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->defaultSort('expense_date', 'desc');
    }
}
