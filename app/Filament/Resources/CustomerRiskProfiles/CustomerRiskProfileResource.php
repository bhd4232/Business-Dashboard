<?php

namespace App\Filament\Resources\CustomerRiskProfiles;

use App\Filament\Clusters\CustomerSuccess;
use App\Filament\Resources\CustomerRiskProfiles\Pages\ListCustomerRiskProfiles;
use App\Models\CustomerRiskProfile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class CustomerRiskProfileResource extends Resource
{
    protected static ?string $model = CustomerRiskProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $cluster = CustomerSuccess::class;

    protected static ?string $navigationLabel = 'Risk Profiles';

    protected static ?int $navigationSort = 1;

    public static function table(Table $table): Table
    {
        return $table->defaultSort('risk_score')->columns([
            TextColumn::make('customer.name')->label('Customer')->searchable(),
            TextColumn::make('phone')->searchable(),
            TextColumn::make('risk_score')->label('Score')->sortable(),
            TextColumn::make('risk_level')->badge()->formatStateUsing(fn (string $state): string => CustomerRiskProfile::LEVELS[$state] ?? $state),
            TextColumn::make('total_courier_orders')->label('Courier Orders')->sortable(),
            TextColumn::make('success_ratio')->suffix('%')->sortable(),
            TextColumn::make('return_ratio')->suffix('%')->sortable(),
            TextColumn::make('cancel_ratio')->suffix('%')->sortable(),
            TextColumn::make('evaluated_at')->dateTime()->sortable(),
        ])->filters([SelectFilter::make('risk_level')->options(CustomerRiskProfile::LEVELS)]);
    }

    public static function canViewAny(): bool
    {
        return Schema::hasTable('customer_risk_profiles') && (Auth::user()?->hasPermission('sales.view') ?? false);
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
        return ['index' => ListCustomerRiskProfiles::route('/')];
    }
}
