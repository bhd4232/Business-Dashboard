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

class ChannelPartnerPayoutsRelationManager extends RelationManager
{
    protected static string $relationship = 'channelPartnerPayouts';

    protected static ?string $title = 'Channel Partner Payout';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('recipient_name')->required(),
            TextInput::make('recipient_bank_name'),
            TextInput::make('recipient_branch'),
            TextInput::make('recipient_routing_number')->label('Routing Number')->helperText('Needed to include this payout in a BEFTN payout batch.'),
            TextInput::make('recipient_account_number'),
            Select::make('payment_method')->options(['cash' => 'Cash', 'bkash' => 'bKash', 'bank' => 'Bank', 'other' => 'Other']),
            TextInput::make('payment_reference'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('investor.name')->label('Channel Partner'),
            TextColumn::make('recipient_name'),
            TextColumn::make('amount')->moneyWithoutTrailingZeroes('BDT'),
            TextColumn::make('payment_status')->badge(),
            TextColumn::make('bank_details')
                ->label('Bank Details')
                ->state(fn ($record): string => $record->hasCompleteBankDetails() ? 'Complete' : 'Incomplete')
                ->badge()
                ->color(fn ($record): string => $record->hasCompleteBankDetails() ? 'success' : 'gray')
                ->tooltip('A payout needs a bank name, routing number, and account number before it can be included in a BEFTN batch.'),
            TextColumn::make('bank_batch')
                ->label('Bank Batch')
                ->state(fn ($record): string => $record->hasActivePayoutItem() ? 'In a batch' : '-')
                ->badge(fn ($record): bool => $record->hasActivePayoutItem())
                ->color('warning'),
            TextColumn::make('paid_at')->date()->placeholder('-'),
        ])->recordActions([
            EditAction::make()->visible(fn ($record): bool => $record->payment_status === 'pending' && ! $record->hasActivePayoutItem() && (auth()->user()?->hasPermission('investments.settle') ?? false)),
            Action::make('markPaid')->label('Mark as Paid')->icon('heroicon-o-check-circle')->color('success')
                ->visible(fn ($record): bool => $record->payment_status === 'pending' && ! $record->hasActivePayoutItem() && (auth()->user()?->hasPermission('investments.settle') ?? false))
                ->schema([
                    Select::make('payment_method')->options(['cash' => 'Cash', 'bkash' => 'bKash', 'bank' => 'Bank', 'other' => 'Other'])->required(),
                    TextInput::make('payment_reference'),
                ])->requiresConfirmation()->action(function ($record, array $data): void {
                    $record->update([...$data, 'payment_status' => 'paid', 'paid_at' => now()->toDateString()]);
                    $settlement = $this->getOwnerRecord();
                    if (! $settlement->payouts()->where('payment_status', 'pending')->exists()) {
                        $settlement->update(['status' => 'paid_out']);
                    }
                    Notification::make()->success()->title('Channel payout marked paid')->send();
                }),
        ]);
    }
}
