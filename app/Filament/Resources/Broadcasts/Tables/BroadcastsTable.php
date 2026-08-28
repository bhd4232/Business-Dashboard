<?php

namespace App\Filament\Resources\Broadcasts\Tables;

use App\Models\Broadcast;
use App\Services\Crm\BroadcastService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BroadcastsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('audience_type')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => Broadcast::AUDIENCE_TYPES[$state] ?? (string) $state),
                TextColumn::make('channel')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => Broadcast::CHANNELS[$state] ?? (string) $state),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'queued', 'sending' => 'warning',
                        'completed' => 'success',
                        'failed' => 'danger',
                        'cancelled' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => Broadcast::STATUSES[$state] ?? (string) $state),
                TextColumn::make('recipients_count')->label('Recipients')->sortable(),
                TextColumn::make('sent_count')->label('Sent')->sortable(),
                TextColumn::make('failed_count')->label('Failed')->sortable(),
                TextColumn::make('creator.name')->label('Created By')->placeholder('-'),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options(Broadcast::STATUSES),
                SelectFilter::make('channel')->options(Broadcast::CHANNELS),
            ])
            ->recordActions([
                Action::make('buildRecipients')
                    ->label('Build recipient list')
                    ->icon('heroicon-o-users')
                    ->visible(fn (Broadcast $record): bool => $record->isEditable())
                    ->action(function (Broadcast $record): void {
                        $count = app(BroadcastService::class)->buildRecipients($record);
                        Notification::make()
                            ->title("Recipient list built: {$count} recipient(s).")
                            ->success()
                            ->send();
                    }),
                Action::make('send')
                    ->label('Send')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription(fn (Broadcast $record): string => "This will message {$record->recipients_count} recipient(s). This cannot be undone.")
                    ->visible(fn (Broadcast $record): bool => $record->isEditable() && $record->recipients_count > 0)
                    ->action(function (Broadcast $record): void {
                        app(BroadcastService::class)->queue($record);
                        Notification::make()
                            ->title('Broadcast queued for sending.')
                            ->success()
                            ->send();
                    }),
                EditAction::make()
                    ->visible(fn (Broadcast $record): bool => $record->isEditable()),
            ]);
    }
}
