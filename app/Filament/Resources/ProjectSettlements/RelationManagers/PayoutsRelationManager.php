<?php

namespace App\Filament\Resources\ProjectSettlements\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PayoutsRelationManager extends RelationManager
{
    protected static string $relationship = 'payouts';

    protected static ?string $title = 'Investor Payouts';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('recipient_name')->required(),
            TextInput::make('recipient_bank_name'),
            TextInput::make('recipient_branch'),
            TextInput::make('recipient_account_number'),
            Select::make('payment_method')->options(['cash' => 'Cash', 'bkash' => 'bKash', 'bank' => 'Bank', 'other' => 'Other']),
            TextInput::make('payment_reference'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('investor.name'),
            TextColumn::make('investment_amount')->money('BDT'),
            TextColumn::make('profit_share_amount')->money('BDT'),
            TextColumn::make('total_payout')->money('BDT'),
            TextColumn::make('recipient_name'),
            TextColumn::make('payment_status')->badge(),
            TextColumn::make('paid_at')->date()->placeholder('-'),
        ])->recordActions([
            EditAction::make()->visible(fn ($record): bool => $record->payment_status === 'pending' && (auth()->user()?->hasPermission('investments.settle') ?? false)),
            Action::make('markPaid')->label('Mark as Paid')->icon('heroicon-o-check-circle')->color('success')
                ->visible(fn ($record): bool => $record->payment_status === 'pending' && (auth()->user()?->hasPermission('investments.settle') ?? false))
                ->requiresConfirmation()->action(function ($record): void {
                    $record->update(['payment_status' => 'paid', 'paid_at' => now()->toDateString()]);
                    $this->syncSettlementStatus();
                    Notification::make()->success()->title('Investor payout marked paid')->send();
                }),
        ]);
    }

    private function syncSettlementStatus(): void
    {
        $settlement = $this->getOwnerRecord();
        if (! $settlement->payouts()->where('payment_status', 'pending')->exists() && ! $settlement->channelPartnerPayouts()->where('payment_status', 'pending')->exists()) {
            $settlement->update(['status' => 'paid_out']);
        }
    }
}
