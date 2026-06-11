<?php

namespace App\Filament\Resources\Products\Tables;

use App\Models\Product;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('category'))
            ->columns([
                ImageColumn::make('image')
                    ->label('Image')
                    ->disk('public')
                    ->height(48)
                    ->square()
                    ->toggleable(),

                TextColumn::make('name')
                    ->label('Product Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable(),

                TextColumn::make('barcode')
                    ->label('Barcode')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('category.name')
                    ->label('Category')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('brand')
                    ->label('Brand')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('sale_price')
                    ->label('Sale Price')
                    ->getStateUsing(fn ($record) => $record->sale_price ?? $record->price)
                    ->money('BDT'),

                TextColumn::make('cost_price')
                    ->label('Cost Price')
                    ->money('BDT')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('stock')
                    ->label('Stock')
                    ->badge()
                    ->color(fn (Product $record): string => $record->isLowStock() ? 'danger' : 'success')
                    ->description(fn (Product $record): ?string => $record->isLowStock() ? 'Low stock' : null),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => Product::STATUSES[$state] ?? 'Available')
                    ->color(fn (?string $state): string => $state === Product::STATUS_COMING_SOON ? 'warning' : 'success'),

                TextColumn::make('reorder_level')
                    ->label('Reorder')
                    ->badge()
                    ->toggleable(),

                TextColumn::make('unit')
                    ->label('Unit')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('vat_rate')
                    ->label('VAT')
                    ->suffix('%')
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name')
                    ->searchable(),

                TernaryFilter::make('is_active')
                    ->label('Active status')
                    ->trueLabel('Active products')
                    ->falseLabel('Inactive products'),

                SelectFilter::make('status')
                    ->label('Product status')
                    ->options(Product::STATUSES),

                Filter::make('low_stock')
                    ->label('Low stock')
                    ->query(fn (Builder $query): Builder => $query->whereColumn('stock', '<=', 'reorder_level'))
                    ->toggle(),

                SelectFilter::make('brand')
                    ->label('Brand')
                    ->options(fn (): array => Product::query()
                        ->whereNotNull('brand')
                        ->where('brand', '!=', '')
                        ->orderBy('brand')
                        ->pluck('brand', 'brand')
                        ->all())
                    ->searchable(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('View')
                    ->color('info')
                    ->icon('heroicon-o-eye'),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
