<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use App\Models\OrderPayment;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Order-level "Payments History" ledger (OrderPayment) — every add/edit/
 * delete here recomputes Order::paid_amount/due_amount via
 * OrderPayment::booted() -> Order::recalculatePaidAmount(). See
 * Order::payments() and OrderForm's dehydrated(false)-on-edit paid_amount/
 * due_amount fields for the rest of this flow.
 *
 * Each action below dispatches 'order-payment-updated' after it completes,
 * so EditOrder/ViewOrder (the pages that embed this relation manager) can
 * refresh their own display of paid_amount/due_amount immediately — those
 * two fields live on the *parent* record, not this table, so nothing here
 * would otherwise tell the parent page anything changed underneath it.
 */
class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Payments History';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('type')
                ->options(OrderPayment::TYPES)
                ->default(OrderPayment::TYPE_ADVANCE)
                ->native(false)
                ->required(),
            Select::make('method')
                ->options(OrderPayment::METHODS)
                ->default('cash')
                ->native(false)
                ->required(),
            TextInput::make('amount')
                ->numeric()
                ->prefix('৳')
                ->minValue(0.01)
                ->required(),
            DatePicker::make('paid_at')
                ->default(now())
                ->native(false)
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
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => OrderPayment::TYPES[$state] ?? str($state)->headline()->toString()),
                TextColumn::make('method')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => OrderPayment::METHODS[$state] ?? str($state)->headline()->toString()),
                TextColumn::make('amount')
                    ->moneyWithoutTrailingZeroes('BDT')
                    ->summarize(Sum::make()->moneyWithoutTrailingZeroes('BDT')),
                TextColumn::make('paid_at')->date(),
                TextColumn::make('receivedBy.name')->label('Received By')->placeholder('System'),
                TextColumn::make('note')->limit(40)->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('paid_at', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->after(fn () => $this->dispatch('order-payment-updated')),
            ])
            ->recordActions([
                EditAction::make()
                    ->after(fn () => $this->dispatch('order-payment-updated')),
                DeleteAction::make()
                    ->after(fn () => $this->dispatch('order-payment-updated')),
            ]);
    }
}
