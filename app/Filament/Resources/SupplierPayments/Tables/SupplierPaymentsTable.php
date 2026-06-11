<?php

namespace App\Filament\Resources\SupplierPayments\Tables;

use App\Models\SupplierPayment;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SupplierPaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['supplier', 'account']))
            ->columns([
                TextColumn::make('payment_number')->label('Payment Number')->searchable()->sortable(),
                TextColumn::make('supplier.name')->searchable()->sortable(),
                TextColumn::make('account.name')->label('Account')->searchable()->sortable(),
                TextColumn::make('payment_date')->date()->sortable(),
                TextColumn::make('amount')->money('BDT')->sortable(),
                TextColumn::make('method')->badge(),
            ])
            ->filters([
                SelectFilter::make('supplier_id')->label('Supplier')->relationship('supplier', 'name')->searchable(),
                SelectFilter::make('account_id')->label('Account')->relationship('account', 'name')->searchable(),
                SelectFilter::make('method')->options(SupplierPayment::METHODS),
            ])
            ->recordActions([ViewAction::make(), EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->defaultSort('payment_date', 'desc');
    }
}
