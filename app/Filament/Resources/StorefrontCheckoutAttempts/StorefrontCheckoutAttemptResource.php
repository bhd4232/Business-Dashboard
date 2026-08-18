<?php

namespace App\Filament\Resources\StorefrontCheckoutAttempts;

use App\Filament\Clusters\Storefront;
use App\Filament\Resources\StorefrontCheckoutAttempts\Pages\ListStorefrontCheckoutAttempts;
use App\Models\StorefrontCheckoutAttempt;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class StorefrontCheckoutAttemptResource extends Resource
{
    protected static ?string $model = StorefrontCheckoutAttempt::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $cluster = Storefront::class;

    protected static ?string $navigationLabel = 'Checkout Attempts';

    protected static ?int $navigationSort = 8;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Attempted')->dateTime()->sortable(),
                TextColumn::make('phone_mask')->label('Phone')->placeholder('-')->searchable(),
                TextColumn::make('email_mask')->label('Email')->placeholder('-')->toggleable(),
                TextColumn::make('ip_mask')->label('IP')->placeholder('-')->toggleable(),
                TextColumn::make('outcome')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        StorefrontCheckoutAttempt::OUTCOME_ACCEPTED => 'success',
                        StorefrontCheckoutAttempt::OUTCOME_BLOCKED => 'danger',
                        StorefrontCheckoutAttempt::OUTCOME_OBSERVED => 'warning',
                        StorefrontCheckoutAttempt::OUTCOME_PENDING_PAYMENT => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('violations')
                    ->label('Policy signals')
                    ->formatStateUsing(fn ($state): string => is_array($state) ? collect($state)->keys()->implode(', ') : '-')
                    ->wrap(),
                TextColumn::make('courier_success_ratio')
                    ->label('Courier success')
                    ->suffix('%')
                    ->placeholder('-')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('required_advance')
                    ->label('Required advance')
                    ->moneyWithoutTrailingZeroes('BDT')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('advance_reasons')
                    ->label('Advance reasons')
                    ->formatStateUsing(fn ($state): string => is_array($state) ? collect($state)->keys()->implode(', ') : '-')
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('cart_total')->label('Cart total')->moneyWithoutTrailingZeroes('BDT')->sortable(),
                TextColumn::make('payment_method')->label('Payment')->badge()->placeholder('-')->toggleable(),
                TextColumn::make('order.order_number')->label('Order')->searchable()->placeholder('-'),
                TextColumn::make('policy_mode')->label('Mode')->badge()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('outcome')->options([
                    StorefrontCheckoutAttempt::OUTCOME_ALLOWED => 'Allowed',
                    StorefrontCheckoutAttempt::OUTCOME_OBSERVED => 'Observed',
                    StorefrontCheckoutAttempt::OUTCOME_BLOCKED => 'Blocked',
                    StorefrontCheckoutAttempt::OUTCOME_PENDING_PAYMENT => 'Pending payment',
                    StorefrontCheckoutAttempt::OUTCOME_ACCEPTED => 'Accepted',
                ]),
            ]);
    }

    public static function canViewAny(): bool
    {
        return Schema::hasTable('storefront_checkout_attempts') && (Auth::user()?->hasPermission('sales.view') ?? false);
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
        return ['index' => ListStorefrontCheckoutAttempts::route('/')];
    }
}
