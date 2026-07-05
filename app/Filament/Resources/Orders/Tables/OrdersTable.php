<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Models\CourierBooking;
use App\Models\CourierProvider;
use App\Models\CustomerRiskProfile;
use App\Models\CustomerRiskReview;
use App\Models\Order;
use App\Services\CompanyContext;
use App\Services\CourierService;
use App\Services\CustomerRiskService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Placeholder;
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
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['customer', 'latestFraudCheck', 'latestRiskReview']))
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

                TextColumn::make('source')
                    ->label('Source')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => Order::SOURCES[$state ?? Order::SOURCE_ADMIN] ?? str($state)->headline()->toString())
                    ->color(fn (?string $state): string => $state === Order::SOURCE_STOREFRONT ? 'warning' : 'gray')
                    ->sortable()
                    ->toggleable(),

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

                TextColumn::make('latestFraudCheck.risk_level')
                    ->label('Risk')
                    ->badge()
                    ->placeholder('Not checked')
                    ->formatStateUsing(fn (?string $state): string => CustomerRiskProfile::LEVELS[$state ?? ''] ?? 'Not checked')
                    ->color(fn (?string $state): string => match ($state) {
                        'low' => 'success', 'medium' => 'warning', 'high', 'blacklisted' => 'danger', default => 'gray',
                    }),

                TextColumn::make('latestRiskReview.status')
                    ->label('Risk Review')
                    ->badge()
                    ->placeholder('Not required')
                    ->formatStateUsing(fn (?string $state): string => CustomerRiskReview::STATUSES[$state ?? ''] ?? 'Not required')
                    ->color(fn (?string $state): string => match ($state) {
                        CustomerRiskReview::STATUS_APPROVED => 'success',
                        CustomerRiskReview::STATUS_REJECTED => 'danger',
                        CustomerRiskReview::STATUS_PENDING => 'warning',
                        default => 'gray',
                    }),

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

                SelectFilter::make('source')
                    ->options(Order::SOURCES),

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
                Action::make('bookPathao')
                    ->label('Book Pathao')
                    ->icon('heroicon-o-truck')
                    ->color('info')
                    ->visible(fn (Order $record): bool => self::canBookCourier($record) && self::hasActiveProvider(CourierProvider::DRIVER_PATHAO))
                    ->schema(fn (Order $record): array => self::pathaoBookingForm($record))
                    ->action(function (Order $record, array $data): void {
                        $provider = CourierProvider::query()->findOrFail($data['courier_provider_id']);
                        app(CourierService::class)->createPathaoBooking($record, $provider, $data);
                    }),
                Action::make('bookRedx')
                    ->label('Book RedX')
                    ->icon('heroicon-o-truck')
                    ->color('danger')
                    ->visible(fn (Order $record): bool => self::canBookCourier($record) && self::hasActiveProvider(CourierProvider::DRIVER_REDX))
                    ->schema(fn (Order $record): array => self::redxBookingForm($record))
                    ->action(function (Order $record, array $data): void {
                        $provider = CourierProvider::query()->findOrFail($data['courier_provider_id']);
                        app(CourierService::class)->createRedxBooking($record, $provider, $data);
                    }),
                Action::make('bookECourier')
                    ->label('Book E-Courier')
                    ->icon('heroicon-o-truck')
                    ->color('success')
                    ->visible(fn (Order $record): bool => self::canBookCourier($record) && self::hasActiveProvider(CourierProvider::DRIVER_ECOURIER))
                    ->schema(fn (Order $record): array => self::ecourierBookingForm($record))
                    ->action(function (Order $record, array $data): void {
                        $provider = CourierProvider::query()->findOrFail($data['courier_provider_id']);
                        app(CourierService::class)->createECourierBooking($record, $provider, $data);
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
                    ->url(fn (Order $record): string => route('orders.print', ['order' => $record, 'print' => 1]))
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
            Placeholder::make('risk_notice')
                ->label('Customer Risk Check')
                ->content(function () use ($record): string {
                    $profile = app(CustomerRiskService::class)->evaluateCustomer($record->customer, $record);

                    return "{$profile->risk_score}/100 — ".(CustomerRiskProfile::LEVELS[$profile->risk_level] ?? $profile->risk_level);
                }),
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

    protected static function hasActiveProvider(string $driver): bool
    {
        return CourierProvider::query()
            ->where('driver', $driver)
            ->where('is_active', true)
            ->exists();
    }

    protected static function providerSelect(string $driver, string $label): Select
    {
        return Select::make('courier_provider_id')
            ->label($label)
            ->options(fn (): array => CourierProvider::query()
                ->where('driver', $driver)
                ->where('is_active', true)
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all())
            ->required()
            ->native(false);
    }

    protected static function pathaoBookingForm(Order $record): array
    {
        return [
            self::providerSelect(CourierProvider::DRIVER_PATHAO, 'Pathao Provider'),
            ...self::courierBookingForm($record, includeProvider: false),
            TextInput::make('store_id')
                ->label('Store ID')
                ->numeric()
                ->helperText('Leave blank to use the provider default store.'),
            TextInput::make('recipient_city')
                ->label('City ID')
                ->numeric()
                ->helperText('Pathao city/zone/area IDs from the merchant panel or API city list.'),
            TextInput::make('recipient_zone')
                ->label('Zone ID')
                ->numeric(),
            TextInput::make('recipient_area')
                ->label('Area ID')
                ->numeric(),
            Select::make('delivery_type')
                ->options([
                    48 => 'Normal Delivery',
                    12 => 'On-Demand Delivery',
                ])
                ->default(48)
                ->native(false),
            TextInput::make('item_weight')
                ->label('Weight (kg)')
                ->numeric()
                ->default(0.5)
                ->minValue(0.1),
        ];
    }

    protected static function redxBookingForm(Order $record): array
    {
        return [
            self::providerSelect(CourierProvider::DRIVER_REDX, 'RedX Provider'),
            ...self::courierBookingForm($record, includeProvider: false),
            TextInput::make('delivery_area')
                ->label('Delivery Area Name')
                ->required()
                ->helperText('RedX area name, e.g. from the RedX areas list.'),
            TextInput::make('delivery_area_id')
                ->label('Delivery Area ID')
                ->numeric()
                ->required(),
            TextInput::make('parcel_weight')
                ->label('Weight (grams)')
                ->numeric()
                ->default(500)
                ->minValue(1),
        ];
    }

    protected static function ecourierBookingForm(Order $record): array
    {
        return [
            self::providerSelect(CourierProvider::DRIVER_ECOURIER, 'E-Courier Provider'),
            ...self::courierBookingForm($record, includeProvider: false),
            TextInput::make('recipient_city')
                ->label('City')
                ->required(),
            TextInput::make('recipient_thana')
                ->label('Thana')
                ->required(),
            TextInput::make('recipient_zip')
                ->label('Post Code')
                ->required(),
            TextInput::make('recipient_area')
                ->label('Area'),
            TextInput::make('package_code')
                ->label('Package Code')
                ->helperText('Leave blank to use the provider default package.'),
        ];
    }

    protected static function canBookCourier(Order $record): bool
    {
        return $record->delivery_status === CourierBooking::STATUS_NOT_BOOKED
            && app(CompanyContext::class)->hasCompany()
            && (int) app(CompanyContext::class)->id() === (int) $record->company_id;
    }
}
