<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use App\Models\Order;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StatusTransitionsRelationManager extends RelationManager
{
    protected static string $relationship = 'statusTransitions';

    protected static ?string $title = 'Status History';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Changed At')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('stage')
                    ->label('Workflow Stage')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => Order::WORKFLOW_STAGES[$state ?? ''] ?? ($state ? str($state)->headline()->toString() : 'Direct update'))
                    ->placeholder('Direct update'),
                TextColumn::make('from_status')
                    ->label('From Order Status')
                    ->formatStateUsing(fn (?string $state): string => Order::STATUSES[$state ?? ''] ?? ($state ? str($state)->headline()->toString() : '-')),
                TextColumn::make('to_status')
                    ->label('To Order Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Order::STATUSES[$state] ?? str($state)->headline()->toString()),
                TextColumn::make('from_delivery_status')
                    ->label('From Delivery')
                    ->formatStateUsing(fn (?string $state): string => Order::DELIVERY_STATUSES[$state ?? ''] ?? ($state ? str($state)->headline()->toString() : '-'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('to_delivery_status')
                    ->label('To Delivery')
                    ->formatStateUsing(fn (?string $state): string => Order::DELIVERY_STATUSES[$state ?? ''] ?? ($state ? str($state)->headline()->toString() : '-')),
                TextColumn::make('changedBy.name')
                    ->label('Changed By')
                    ->placeholder('System'),
                TextColumn::make('source')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str($state)->headline()->toString())
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('reason')
                    ->wrap()
                    ->placeholder('-'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
