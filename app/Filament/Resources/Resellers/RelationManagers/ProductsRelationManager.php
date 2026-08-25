<?php

namespace App\Filament\Resources\Resellers\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Read-only: which products this reseller picked for their storefront.
 * Curation itself stays self-service on the reseller's own account page
 * (App\Http\Controllers\Storefront\ResellerStoreController) -- staff get
 * visibility here, not an admin-side edit path, per the owner's answer.
 */
class ProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'resellerCatalog';

    protected static ?string $title = 'Store Products';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('sku')->searchable(),
                TextColumn::make('sale_price')->moneyWithoutTrailingZeroes('BDT'),
                IconColumn::make('pivot.is_active')->label('Shown in store')->boolean(),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
