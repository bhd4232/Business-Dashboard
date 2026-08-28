<?php

namespace App\Filament\Resources\Resellers\RelationManagers;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Every order placed through this reseller's storefront (Order::reseller(),
 * reseller_customer_id) -- distinct from the reseller's own purchase
 * history as a buyer, which stays on the regular Customer record. Read-only
 * here: order management already happens on the Orders resource itself.
 */
class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'resellerOrders';

    protected static ?string $title = 'Store Orders';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')->label('Order #')->searchable(),
                TextColumn::make('customer_name')->label('Buyer'),
                TextColumn::make('total_amount')->moneyWithoutTrailingZeroes('BDT'),
                TextColumn::make('status')->badge(),
                TextColumn::make('delivery_status')->badge()->placeholder('-'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('open')
                    ->label('Open')
                    ->url(fn (Order $record): string => OrderResource::getUrl('view', ['record' => $record])),
            ]);
    }
}
