<?php

namespace App\Filament\Resources\Broadcasts\RelationManagers;

use App\Models\BroadcastRecipient;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RecipientsRelationManager extends RelationManager
{
    protected static string $relationship = 'recipients';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->placeholder('-'),
                TextColumn::make('phone')->searchable(),
                TextColumn::make('recipient_type')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => BroadcastRecipient::TYPES[$state] ?? (string) $state),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'sent' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => BroadcastRecipient::STATUSES[$state] ?? (string) $state),
                TextColumn::make('channel_used')->placeholder('-'),
                TextColumn::make('error')->limit(60)->tooltip(fn (?BroadcastRecipient $record): ?string => $record?->error)->placeholder('-'),
                TextColumn::make('sent_at')->dateTime()->placeholder('-'),
            ])
            ->defaultSort('id')
            ->filters([
                SelectFilter::make('status')->options(BroadcastRecipient::STATUSES),
            ]);
    }
}
