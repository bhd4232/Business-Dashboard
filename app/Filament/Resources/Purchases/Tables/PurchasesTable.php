<?php

namespace App\Filament\Resources\Purchases\Tables;

use App\Models\Purchase;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PurchasesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('supplier'))
            ->columns([
                TextColumn::make('purchase_number')
                    ->label('Purchase Number')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('purchase_date')
                    ->date()
                    ->sortable(),

                TextColumn::make('total_amount')
                    ->money('BDT')
                    ->sortable(),

                TextColumn::make('china_to_bd_cost_total')
                    ->label('China to BD Costs')
                    ->getStateUsing(fn (Purchase $record): float => $record->chinaToBdCostTotal())
                    ->money('BDT')
                    ->toggleable(),

                TextColumn::make('landed_cost_total')
                    ->label('Landed Cost')
                    ->getStateUsing(fn (Purchase $record): float => $record->landedCostTotal())
                    ->money('BDT')
                    ->toggleable(),

                TextColumn::make('landed_unit_costs')
                    ->label('Landed Unit Costs')
                    ->getStateUsing(fn (Purchase $record): string => $record->landedCostPerUnitSummary())
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('custom_costs')
                    ->label('Custom Fields')
                    ->getStateUsing(fn (Purchase $record): string => $record->customCostsSummary())
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('paid_amount')
                    ->money('BDT')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('due_amount')
                    ->money('BDT')
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'received' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(Purchase::STATUSES),

                SelectFilter::make('supplier_id')
                    ->label('Supplier')
                    ->relationship('supplier', 'name')
                    ->searchable(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
