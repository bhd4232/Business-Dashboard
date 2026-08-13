<?php

namespace App\Filament\Resources\CourierReturns;

use App\Filament\Clusters\Courier;
use App\Filament\Resources\CourierReturns\Pages\ListCourierReturns;
use App\Models\CourierReturn;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class CourierReturnResource extends Resource
{
    protected static ?string $model = CourierReturn::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnLeft;

    protected static ?string $cluster = Courier::class;

    protected static ?string $navigationLabel = 'Returns';

    protected static ?int $navigationSort = 4;

    protected static bool $shouldRegisterNavigation = false;

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['booking.order', 'provider']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('booking.tracking_id')
                    ->label('Tracking ID')
                    ->searchable(),
                TextColumn::make('booking.order.order_number')
                    ->label('Invoice')
                    ->searchable(),
                TextColumn::make('provider.name')
                    ->label('Provider'),
                TextColumn::make('provider_reference')
                    ->label('Return Reference')
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => CourierReturn::STATUSES[$state ?? ''] ?? str($state)->headline()->toString()),
                TextColumn::make('reason')
                    ->limit(60)
                    ->placeholder('-'),
                TextColumn::make('requested_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(CourierReturn::STATUSES),
            ]);
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
        return [
            'index' => ListCourierReturns::route('/'),
        ];
    }
}
