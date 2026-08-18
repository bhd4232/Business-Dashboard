<?php

namespace App\Filament\Resources\ProjectSettlements\RelationManagers;

use Filament\Actions\Action;
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
            Select::make('payment_method')->options(['cash' => 'Cash', 'bkash' => 'bKash', 'bank' => 'Bank', 'other' => 'Other']),
            TextInput::make('payment_reference'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('investor.name')->label('Channel Partner'),
            TextColumn::make('amount')->moneyWithoutTrailingZeroes('BDT'),
            TextColumn::make('payment_status')->badge(),
            TextColumn::make('paid_at')->date()->placeholder('-'),
        ])->recordActions([
            Action::make('markPaid')->label('Mark as Paid')->icon('heroicon-o-check-circle')->color('success')
                ->visible(fn ($record): bool => $record->payment_status === 'pending' && (auth()->user()?->hasPermission('investments.settle') ?? false))
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
