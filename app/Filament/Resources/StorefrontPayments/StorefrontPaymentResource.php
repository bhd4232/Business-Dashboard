<?php

namespace App\Filament\Resources\StorefrontPayments;

use App\Filament\Resources\StorefrontPayments\Pages\ListStorefrontPayments;
use App\Models\StorefrontPayment;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class StorefrontPaymentResource extends Resource
{
    protected static ?string $model = StorefrontPayment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Storefront';

    protected static ?string $navigationLabel = 'Storefront Payments';

    protected static ?string $recordTitleAttribute = 'invoice_id';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order.order_number')
                    ->label('Order')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('gateway')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'manual_bkash' => 'bKash (Manual)',
                        'manual_nagad' => 'Nagad (Manual)',
                        'zinipay' => 'ZiniPay',
                        default => ucfirst($state),
                    }),
                TextColumn::make('amount')
                    ->money('BDT')
                    ->sortable(),
                TextColumn::make('payment_method')
                    ->label('Sender number'),
                TextColumn::make('transaction_id')
                    ->label('Transaction ID')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'failed' => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(StorefrontPayment::STATUSES),
                SelectFilter::make('gateway')->options([
                    'manual_bkash' => 'bKash (Manual)',
                    'manual_nagad' => 'Nagad (Manual)',
                    'zinipay' => 'ZiniPay',
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('verify')
                    ->label('Verify')
                    ->color('success')
                    ->icon(Heroicon::OutlinedCheck)
                    ->visible(fn (StorefrontPayment $record): bool => $record->status === StorefrontPayment::STATUS_PENDING
                        && in_array($record->gateway, ['manual_bkash', 'manual_nagad'], true))
                    ->requiresConfirmation()
                    ->action(function (StorefrontPayment $record): void {
                        $record->update(['status' => StorefrontPayment::STATUS_COMPLETED]);
                        Notification::make()->title('Payment verified.')->success()->send();
                    }),
                Action::make('reject')
                    ->label('Reject')
                    ->color('danger')
                    ->icon(Heroicon::OutlinedXMark)
                    ->visible(fn (StorefrontPayment $record): bool => $record->status === StorefrontPayment::STATUS_PENDING
                        && in_array($record->gateway, ['manual_bkash', 'manual_nagad'], true))
                    ->requiresConfirmation()
                    ->action(function (StorefrontPayment $record): void {
                        $record->update(['status' => StorefrontPayment::STATUS_FAILED]);
                        Notification::make()->title('Payment rejected.')->warning()->send();
                    }),
            ]);
    }

    public static function canViewAny(): bool
    {
        return Auth::user()?->canManageSettings() ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStorefrontPayments::route('/'),
        ];
    }
}
