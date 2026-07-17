<?php

namespace App\Filament\Resources\Leads\Schemas;

use App\Models\Lead;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LeadInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Lead')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('phone'),
                        TextEntry::make('email')->placeholder('-'),
                        TextEntry::make('source')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => Lead::SOURCES[$state] ?? (string) $state),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'new' => 'gray',
                                'contacted' => 'warning',
                                'quoted' => 'info',
                                'won' => 'success',
                                'lost' => 'danger',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (?string $state): string => Lead::STATUSES[$state] ?? (string) $state),
                        TextEntry::make('estimated_value')->money('BDT')->placeholder('-'),
                        TextEntry::make('assignedUser.name')->label('Assigned To')->placeholder('-'),
                        TextEntry::make('next_follow_up_at')
                            ->label('Next Follow-up')
                            ->dateTime()
                            ->placeholder('-')
                            ->color(fn ($state) => $state?->isPast() ? 'danger' : null),
                    ])
                    ->columns(2),

                Section::make('Conversion')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('convertedCustomer.name')->label('Converted Customer')->placeholder('Not converted'),
                        TextEntry::make('convertedOrder.order_number')->label('Converted Order')->placeholder('Not converted'),
                    ])
                    ->columns(2),

                Section::make('Details')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('interest')->label('Interested In')->placeholder('-'),
                        TextEntry::make('note')->placeholder('-'),
                        TextEntry::make('creator.name')->label('Created By')->placeholder('-'),
                        TextEntry::make('created_at')->dateTime(),
                    ])
                    ->columns(2),
            ]);
    }
}
