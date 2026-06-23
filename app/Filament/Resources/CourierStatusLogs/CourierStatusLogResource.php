<?php

namespace App\Filament\Resources\CourierStatusLogs;

use App\Filament\Resources\CourierStatusLogs\Pages\ListCourierStatusLogs;
use App\Models\CourierBooking;
use App\Models\CourierStatusLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class CourierStatusLogResource extends Resource
{
    protected static ?string $model = CourierStatusLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static string|UnitEnum|null $navigationGroup = 'Courier';

    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->columns([
            TextColumn::make('booking.tracking_id')->label('Tracking ID')->searchable(),
            TextColumn::make('booking.order.order_number')->label('Invoice')->searchable(),
            TextColumn::make('from_status')->placeholder('New')->badge(),
            TextColumn::make('to_status')->badge(),
            TextColumn::make('note')->limit(60),
            TextColumn::make('creator.name')->label('Changed by')->placeholder('System'),
            TextColumn::make('created_at')->dateTime()->sortable(),
        ])->filters([SelectFilter::make('to_status')->options(CourierBooking::STATUSES)]);
    }

    public static function canViewAny(): bool
    {
        return Auth::user()?->hasPermission('sales.view') ?? false;
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
        return ['index' => ListCourierStatusLogs::route('/')];
    }
}
