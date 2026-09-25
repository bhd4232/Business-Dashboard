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
            // The payment method is the account the money came into (Cash,
            // bKash, bank… from the Accounts page), so the payment posts to
            // that account's ledger and finance updates by itself. The
            // generic method list is only a fallback for a company that has
            // no money accounts set up yet.
            Select::make('account_id')
                ->label(__('Payment Method'))
                ->options(fn (): array => OrderPayment::accountOptions())
                ->searchable()
                ->native(false)
                ->visible(fn (): bool => OrderPayment::accountOptions() !== [])
                ->required(fn (): bool => OrderPayment::accountOptions() !== [])
                ->helperText(__('The account the payment was received into. Its balance updates automatically.')),
            Select::make('method')
                ->options(OrderPayment::METHODS)
                ->default('cash')
                ->native(false)
                ->visible(fn (): bool => OrderPayment::accountOptions() === [])
                ->required(fn (): bool => OrderPayment::accountOptions() === []),
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
            ->modifyQueryUsing(fn ($query) => $query->with('account'))
            ->columns([
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => OrderPayment::TYPES[$state] ?? str($state)->headline()->toString()),
                TextColumn::make('method')
                    ->label(__('Payment Method'))
                    ->badge()
                    ->formatStateUsing(fn (OrderPayment $record, string $state): string => $record->account?->name
                        ?? OrderPayment::METHODS[$state]
                        ?? str($state)->headline()->toString()),
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
