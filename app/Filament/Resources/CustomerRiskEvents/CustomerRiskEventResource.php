<?php

namespace App\Filament\Resources\CustomerRiskEvents;

use App\Filament\Resources\CustomerRiskEvents\Pages\ListCustomerRiskEvents;
use App\Models\CustomerRiskEvent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use UnitEnum;

class CustomerRiskEventResource extends Resource
{
    protected static ?string $model = CustomerRiskEvent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'Customer Success';

    protected static ?int $navigationSort = 4;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('customer.name')->label('Customer')->searchable()->placeholder('-'),
                TextColumn::make('order.order_number')->label('Order')->searchable()->placeholder('-'),
                TextColumn::make('event_type')->badge()->searchable(),
                TextColumn::make('score_change')->placeholder('0')->sortable(),
                TextColumn::make('metadata')->label('Metadata')->formatStateUsing(fn ($state): string => is_array($state) ? json_encode($state) : (string) $state)->limit(60),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ]);
    }

    public static function canViewAny(): bool
    {
        return Schema::hasTable('customer_risk_events') && (Auth::user()?->hasPermission('sales.view') ?? false);
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
        return ['index' => ListCustomerRiskEvents::route('/')];
    }
}
