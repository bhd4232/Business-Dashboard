<?php

namespace App\Filament\Resources\Customers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('phone')
                    ->searchable(),

                TextColumn::make('email')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('customer_type')
                    ->label('Type')
                    ->badge()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('customer_source')
                    ->label('Source')
                    ->badge()
                    ->placeholder('-')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('current_balance')
                    ->money('BDT')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('customer_type')
                    ->label('Customer type')
                    ->options(\App\Models\Customer::TYPES),

                SelectFilter::make('customer_source')
                    ->label('Customer source')
                    ->options(\App\Models\Customer::SOURCES),

                TernaryFilter::make('is_active')
                    ->label('Active status')
                    ->trueLabel('Active customers')
                    ->falseLabel('Inactive customers'),
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
