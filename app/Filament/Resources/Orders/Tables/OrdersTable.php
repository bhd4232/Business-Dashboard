<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Models\CourierBooking;
use App\Models\CourierProvider;
use App\Models\Order;
use App\Services\CompanyContext;
use App\Services\CourierService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('customer'))
            ->columns([
                TextColumn::make('order_number')
                    ->label('Invoice Number')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('order_date')
                    ->label('Sale Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('total_amount')
                    ->label('Total Amount')
                    ->money('BDT')
                    ->sortable(),

                TextColumn::make('paid_amount')
                    ->money('BDT')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('due_amount')
                    ->money('BDT')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'confirmed', 'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('delivery_status')
                    ->label('Delivery')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => Order::DELIVERY_STATUSES[$state ?? CourierBooking::STATUS_NOT_BOOKED] ?? str($state)->headline()->toString())
                    ->color(fn (?string $state): string => match ($state) {
                        CourierBooking::STATUS_DELIVERED => 'success',
                        CourierBooking::STATUS_RETURNED, CourierBooking::STATUS_CANCELLED, CourierBooking::STATUS_FAILED => 'danger',
                        CourierBooking::STATUS_BOOKED, CourierBooking::STATUS_PICKED_UP, CourierBooking::STATUS_IN_TRANSIT => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(Order::STATUSES),

                SelectFilter::make('delivery_status')
                    ->label('Delivery')
                    ->options(Order::DELIVERY_STATUSES),

                SelectFilter::make('customer_id')
                    ->label('Customer')
                    ->relationship('customer', 'name')
                    ->searchable(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('bookCourier')
                    ->label('Book courier')
                    ->icon('heroicon-o-truck')
                    ->visible(fn (Order $record): bool => self::canBookCourier($record))
                    ->schema(fn (Order $record): array => self::courierBookingForm($record))
                    ->action(function (Order $record, array $data): void {
                        app(CourierService::class)->createManualBooking($record, $data);
                    }),
                Action::make('bookSteadfast')
                    ->label('Book Steadfast')
                    ->icon('heroicon-o-truck')
                    ->color('warning')
                    ->visible(fn (Order $record): bool => self::canBookCourier($record) && CourierProvider::query()
                        ->where('driver', CourierProvider::DRIVER_STEADFAST)
                        ->where('is_active', true)
                        ->exists())
                    ->schema(fn (Order $record): array => self::steadfastBookingForm($record))
                    ->action(function (Order $record, array $data): void {
                        $provider = CourierProvider::query()->findOrFail($data['courier_provider_id']);
                        app(CourierService::class)->createSteadfastBooking($record, $provider, $data);
                    }),
                Action::make('markDelivered')
                    ->label('Delivered')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Order $record): bool => filled($record->latestCourierBooking) && ! in_array($record->delivery_status, [
                        CourierBooking::STATUS_DELIVERED,
                        CourierBooking::STATUS_RETURNED,
                        CourierBooking::STATUS_CANCELLED,
                    ], true))
                    ->requiresConfirmation()
                    ->action(function (Order $record): void {
                        app(CourierService::class)->updateStatus($record->latestCourierBooking, CourierBooking::STATUS_DELIVERED, 'Marked delivered from order list.');
                    }),
                Action::make('markReturned')
                    ->label('Returned')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->visible(fn (Order $record): bool => filled($record->latestCourierBooking) && ! in_array($record->delivery_status, [
                        CourierBooking::STATUS_DELIVERED,
                        CourierBooking::STATUS_RETURNED,
                        CourierBooking::STATUS_CANCELLED,
                    ], true))
                    ->requiresConfirmation()
                    ->action(function (Order $record): void {
                        app(CourierService::class)->updateStatus($record->latestCourierBooking, CourierBooking::STATUS_RETURNED, 'Marked returned from order list.');
                    }),
                Action::make('print')
                    ->url(fn (Order $record): string => route('orders.print', $record))
                    ->openUrlInNewTab(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    protected static function courierBookingForm(Order $record, bool $includeProvider = true): array
    {
        $record->loadMissing('customer');

        $schema = [
            TextInput::make('tracking_id')
                ->label('Tracking ID')
                ->helperText('Leave blank to auto-generate a manual tracking ID.')
                ->maxLength(255),
            TextInput::make('recipient_name')
                ->required()
                ->default($record->customer_name ?: $record->customer?->name)
                ->maxLength(255),
            TextInput::make('recipient_phone')
                ->tel()
                ->default($record->customer?->phone)
                ->maxLength(255),
            Textarea::make('recipient_address')
                ->default($record->customer?->address)
                ->rows(3),
            TextInput::make('cod_amount')
                ->numeric()
                ->prefix('BDT')
                ->default((float) $record->due_amount)
                ->minValue(0),
            Textarea::make('note')
                ->rows(2),
        ];

        if (! $includeProvider) {
            return $schema;
        }

        return [
            Select::make('courier_provider_id')
                ->label('Custom Provider')
                ->options(fn (): array => CourierProvider::query()
                    ->where('driver', CourierProvider::DRIVER_MANUAL)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->placeholder('Default Custom')
                ->native(false),
            ...$schema,
        ];
    }

    protected static function steadfastBookingForm(Order $record): array
    {
        return [
            Select::make('courier_provider_id')
                ->label('Steadfast Provider')
                ->options(fn (): array => CourierProvider::query()
                    ->where('driver', CourierProvider::DRIVER_STEADFAST)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->required()
                ->native(false),
            ...self::courierBookingForm($record, includeProvider: false),
            TextInput::make('alternative_phone')
                ->tel()
                ->maxLength(255),
            TextInput::make('recipient_email')
                ->email()
                ->default($record->customer?->email)
                ->maxLength(255),
            TextInput::make('item_description')
                ->maxLength(255),
            TextInput::make('total_lot')
                ->numeric()
                ->minValue(1),
            Select::make('delivery_type')
                ->options([
                    0 => 'Home Delivery',
                    1 => 'Point Delivery / Hub Pickup',
                ])
                ->default(0)
                ->native(false),
        ];
    }

    protected static function canBookCourier(Order $record): bool
    {
        return $record->delivery_status === CourierBooking::STATUS_NOT_BOOKED
            && app(CompanyContext::class)->hasCompany()
            && (int) app(CompanyContext::class)->id() === (int) $record->company_id;
    }
}
