<?php

namespace App\Filament\Resources\Offers\Tables;

use App\Filament\Resources\Offers\OfferResource;
use App\Models\Offer;
use App\Services\OfferPricingService;
use App\Support\MoneyFormatter;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OffersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => Offer::TYPES[$state] ?? (string) $state)
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => Offer::STATUSES[$state] ?? (string) $state)
                    ->color(fn (?string $state): string => match ($state) {
                        Offer::STATUS_PUBLISHED => 'success',
                        Offer::STATUS_ARCHIVED => 'gray',
                        default => 'warning',
                    })
                    ->sortable(),
                TextColumn::make('company.name')
                    ->label('Company')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('final_price')
                    ->label('Price')
                    ->state(fn (Offer $record): string => MoneyFormatter::currency(app(OfferPricingService::class)->finalPrice($record))),
                TextColumn::make('items_count')
                    ->label('Products')
                    ->counts('items')
                    ->badge(),
                IconColumn::make('is_ai_generated')
                    ->label('AI Generated')
                    ->boolean()
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')->options(Offer::TYPES),
                SelectFilter::make('status')->options(Offer::STATUSES),
            ])
            ->defaultSort('updated_at', 'desc')
            ->recordActions([
                EditAction::make(),
                Action::make('previewLandingPage')
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Offer $record): string => OfferResource::previewUrl($record))
                    ->openUrlInNewTab(),
                Action::make('openLandingPage')
                    ->label('Open Page')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Offer $record): string => OfferResource::publicUrl($record))
                    ->openUrlInNewTab()
                    ->visible(fn (Offer $record): bool => $record->status === Offer::STATUS_PUBLISHED && filled($record->company?->domain)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
