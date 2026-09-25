<?php

namespace App\Filament\Resources\ExpenseScans\Tables;

use App\Models\ExpenseScan;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExpenseScansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with('user')
                ->withCount('items')
                ->withSum('items', 'amount'))
            ->columns([
                TextColumn::make('id')->label('Scan #')->sortable(),
                TextColumn::make('created_at')->label('Uploaded')->dateTime()->sortable(),
                TextColumn::make('user.name')->label('Uploaded by')->placeholder('—'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ExpenseScan::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => ExpenseScan::statusColor($state)),
                TextColumn::make('items_count')->label('Lines'),
                TextColumn::make('items_sum_amount')->label('Total')->moneyWithoutTrailingZeroes('BDT')->placeholder('—'),
                TextColumn::make('published_at')->label('Published')->dateTime()->sortable()->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->options(ExpenseScan::STATUSES),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->label('Review'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
