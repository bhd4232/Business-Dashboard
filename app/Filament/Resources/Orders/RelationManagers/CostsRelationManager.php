<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use App\Models\OrderCost;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Order-level "Associated Costs" ledger (OrderCost) — Purchase / Courier
 * Delivery / COD / Other cost lines, itemized and notatable rather than a
 * single settings-driven number. Feeds Order::profit() on OrderInfolist's
 * Totals section.
 */
class CostsRelationManager extends RelationManager
{
    protected static string $relationship = 'costs';

    protected static ?string $title = 'Associated Costs';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('cost_head')
                ->label('Cost Head')
                ->options(OrderCost::HEADS)
                ->default(OrderCost::HEAD_OTHER)
                ->native(false)
                ->required(),
            TextInput::make('amount')
                ->numeric()
                ->prefix('৳')
                ->minValue(0)
                ->required(),
            Textarea::make('note')
                ->rows(2)
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('cost_head')
                    ->label('Cost Head')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => OrderCost::HEADS[$state] ?? str($state)->headline()->toString()),
                TextColumn::make('amount')
                    ->moneyWithoutTrailingZeroes('BDT')
                    ->summarize(Sum::make()->moneyWithoutTrailingZeroes('BDT')),
                TextColumn::make('note')->limit(40)->placeholder('-'),
                TextColumn::make('updated_at')->dateTime()->label('Updated At'),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
