<?php

namespace App\Filament\Resources\PayoutBatches\RelationManagers;

use App\Services\Payouts\PayoutBatchService;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Items';

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('recipient_name'),
            TextColumn::make('recipient_bank_name')->label('Bank'),
            TextColumn::make('recipient_branch')->label('Branch'),
            TextColumn::make('recipient_account_number')->label('Account No.')->placeholder('-'),
            TextColumn::make('recipient_mfs_number')->label('MFS Number')->placeholder('-'),
            TextColumn::make('amount')->moneyWithoutTrailingZeroes('BDT'),
            TextColumn::make('status')->badge(),
            TextColumn::make('bank_transaction_reference')->label('Bank Reference')->placeholder('-'),
            TextColumn::make('failure_reason')->placeholder('-'),
        ])->recordActions([
            Action::make('reconcile')
                ->label('Reconcile')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('warning')
                ->visible(fn ($record): bool => $record->status === 'in_batch'
                    && $this->getOwnerRecord()->status === 'submitted_to_bank'
                    && (auth()->user()?->hasPermission('investments.settle') ?? false))
                ->schema([
                    Radio::make('status')->label('Outcome')->options(['paid' => 'Paid', 'failed' => 'Failed / Returned'])->required()->live(),
                    TextInput::make('bank_transaction_reference')->label('Bank Transaction Reference')->visible(fn (Get $get): bool => $get('status') === 'paid'),
                    TextInput::make('failure_reason')->label('Reason')->visible(fn (Get $get): bool => $get('status') === 'failed'),
                ])
                ->requiresConfirmation()
                ->action(function ($record, array $data): void {
                    app(PayoutBatchService::class)->reconcileItem(
                        $record,
                        $data['status'],
                        $data['bank_transaction_reference'] ?? null,
                        $data['failure_reason'] ?? null,
                    );
                    Notification::make()->success()->title('Item reconciled')->send();
                }),
        ]);
    }
}
