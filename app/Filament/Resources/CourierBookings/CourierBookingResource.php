<?php

namespace App\Filament\Resources\CourierBookings;

use App\Filament\Resources\CourierBookings\Pages\ListCourierBookings;
use App\Filament\Resources\CourierBookings\Pages\ViewCourierBooking;
use App\Models\CourierBooking;
use App\Services\CourierService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use UnitEnum;

class CourierBookingResource extends Resource
{
    protected static ?string $model = CourierBooking::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Courier';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'tracking_id';

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['order.customer', 'provider']))
            ->columns([
                TextColumn::make('tracking_id')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('provider_reference')
                    ->label('Consignment ID')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('order.order_number')
                    ->label('Invoice')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('recipient_name')
                    ->searchable(),
                TextColumn::make('provider.name')
                    ->label('Provider'),
                TextColumn::make('cod_amount')
                    ->money('BDT')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => CourierBooking::STATUSES[$state ?? ''] ?? str($state)->headline()->toString())
                    ->color(fn (?string $state): string => self::statusColor($state))
                    ->sortable(),
                TextColumn::make('booked_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(CourierBooking::STATUSES),
            ])
            ->recordActions([
                ViewAction::make(),
                self::statusAction(),
                self::syncSteadfastAction(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Booking')
                ->schema([
                    TextEntry::make('tracking_id'),
                    TextEntry::make('provider_reference')->label('Consignment ID'),
                    TextEntry::make('provider.name')->label('Provider'),
                    TextEntry::make('order.order_number')->label('Invoice'),
                    TextEntry::make('status')
                        ->badge()
                        ->formatStateUsing(fn (?string $state): string => CourierBooking::STATUSES[$state ?? ''] ?? str($state)->headline()->toString()),
                    TextEntry::make('recipient_name'),
                    TextEntry::make('recipient_phone'),
                    TextEntry::make('recipient_address'),
                    TextEntry::make('cod_amount')->money('BDT'),
                ])
                ->columns(2),

            Section::make('Status Logs')
                ->schema([
                    RepeatableEntry::make('statusLogs')
                        ->label('')
                        ->schema([
                            TextEntry::make('created_at')->dateTime(),
                            TextEntry::make('from_status')
                                ->placeholder('New')
                                ->formatStateUsing(fn (?string $state): string => CourierBooking::STATUSES[$state ?? ''] ?? str($state)->headline()->toString()),
                            TextEntry::make('to_status')
                                ->badge()
                                ->formatStateUsing(fn (?string $state): string => CourierBooking::STATUSES[$state ?? ''] ?? str($state)->headline()->toString()),
                            TextEntry::make('note')->placeholder('-'),
                        ])
                        ->columns(4)
                        ->contained(false),
                ]),
        ]);
    }

    public static function canViewAny(): bool
    {
        return SchemaFacade::hasTable('courier_bookings') && (Auth::user()?->hasPermission('sales.view') ?? false);
    }

    public static function canView(Model $record): bool
    {
        return SchemaFacade::hasTable('courier_bookings') && (Auth::user()?->hasPermission('sales.view') ?? false);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCourierBookings::route('/'),
            'view' => ViewCourierBooking::route('/{record}'),
        ];
    }

    public static function statusAction(): Action
    {
        return Action::make('updateStatus')
            ->label('Update status')
            ->icon('heroicon-o-arrow-path')
            ->schema([
                Select::make('status')
                    ->options(CourierBooking::STATUSES)
                    ->required()
                    ->native(false),
                Textarea::make('note')
                    ->rows(3),
            ])
            ->action(function (CourierBooking $record, array $data): void {
                app(CourierService::class)->updateStatus($record, $data['status'], $data['note'] ?? null);
            });
    }

    public static function syncSteadfastAction(): Action
    {
        return Action::make('syncSteadfast')
            ->label('Sync Steadfast')
            ->icon('heroicon-o-arrow-path')
            ->visible(fn (CourierBooking $record): bool => $record->provider?->driver === \App\Models\CourierProvider::DRIVER_STEADFAST)
            ->action(function (CourierBooking $record): void {
                app(CourierService::class)->syncSteadfastStatus($record);
            });
    }

    protected static function statusColor(?string $status): string
    {
        return match ($status) {
            CourierBooking::STATUS_DELIVERED => 'success',
            CourierBooking::STATUS_RETURNED, CourierBooking::STATUS_CANCELLED, CourierBooking::STATUS_FAILED => 'danger',
            CourierBooking::STATUS_BOOKED, CourierBooking::STATUS_PICKED_UP, CourierBooking::STATUS_IN_TRANSIT => 'warning',
            default => 'gray',
        };
    }
}
