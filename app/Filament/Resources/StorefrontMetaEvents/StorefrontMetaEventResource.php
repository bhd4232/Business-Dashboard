<?php

namespace App\Filament\Resources\StorefrontMetaEvents;

use App\Filament\Clusters\Storefront;
use App\Filament\Resources\StorefrontMetaEvents\Pages\ListStorefrontMetaEvents;
use App\Jobs\RetryStorefrontMetaEventJob;
use App\Models\StorefrontMetaEvent;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class StorefrontMetaEventResource extends Resource
{
    protected static ?string $model = StorefrontMetaEvent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static ?string $cluster = Storefront::class;

    protected static ?string $navigationLabel = 'Meta Events';

    protected static ?int $navigationSort = 9;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Created')->dateTime()->sortable(),
                TextColumn::make('event_name')->label('Event')->badge()->searchable(),
                TextColumn::make('pixel_id')->label('Pixel / Dataset')->copyable()->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        StorefrontMetaEvent::STATUS_SENT => 'success',
                        StorefrontMetaEvent::STATUS_FAILED => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('order.order_number')->label('Order')->searchable()->placeholder('-'),
                TextColumn::make('event_id')->label('Deduplication ID')->copyable()->searchable()->toggleable(),
                TextColumn::make('payload_summary.value')->label('Value')->money('BDT')->placeholder('-'),
                TextColumn::make('payload_summary.workflow_stage')->label('Workflow Stage')->badge()->placeholder('-')->toggleable(),
                TextColumn::make('attempts')->numeric()->sortable(),
                TextColumn::make('sent_at')->dateTime()->placeholder('-')->sortable(),
                TextColumn::make('error')->limit(80)->wrap()->placeholder('-')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('event_name')
                    ->label('Event')
                    ->options(fn (): array => StorefrontMetaEvent::query()
                        ->distinct()
                        ->orderBy('event_name')
                        ->pluck('event_name', 'event_name')
                        ->all()),
                SelectFilter::make('status')->options([
                    StorefrontMetaEvent::STATUS_PENDING => 'Pending',
                    StorefrontMetaEvent::STATUS_SENT => 'Sent',
                    StorefrontMetaEvent::STATUS_FAILED => 'Failed',
                ]),
            ])
            ->recordActions([
                Action::make('retry')
                    ->label('Retry')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (StorefrontMetaEvent $record): bool => $record->status !== StorefrontMetaEvent::STATUS_SENT
                        && (Auth::user()?->hasPermission('sales.manage') ?? false))
                    ->action(function (StorefrontMetaEvent $record): void {
                        RetryStorefrontMetaEventJob::dispatch($record->getKey());
                        Notification::make()
                            ->title('Meta event retry queued')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function canViewAny(): bool
    {
        return Schema::hasTable('storefront_meta_events') && (Auth::user()?->hasPermission('sales.view') ?? false);
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
        return ['index' => ListStorefrontMetaEvents::route('/')];
    }
}
