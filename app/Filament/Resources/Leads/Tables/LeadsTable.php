<?php

namespace App\Filament\Resources\Leads\Tables;

use App\Models\Lead;
use App\Services\Crm\LeadConversionService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LeadsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('phone')->searchable(),
                TextColumn::make('source')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => Lead::SOURCES[$state] ?? (string) $state),
                TextColumn::make('status')
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
                TextColumn::make('estimated_value')->money('BDT')->sortable()->placeholder('-'),
                TextColumn::make('assignedUser.name')->label('Assigned To')->placeholder('-'),
                TextColumn::make('next_follow_up_at')
                    ->label('Next Follow-up')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('-')
                    ->color(fn ($state) => $state?->isPast() ? 'danger' : null),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options(Lead::STATUSES),
                SelectFilter::make('source')->options(Lead::SOURCES),
                SelectFilter::make('assigned_to')->relationship('assignedUser', 'name')->label('Assigned To'),
                Filter::make('follow_up_today')
                    ->label("Today's follow-ups")
                    ->query(fn (Builder $query): Builder => $query->whereDate('next_follow_up_at', today())),
                Filter::make('follow_up_overdue')
                    ->label('Overdue follow-ups')
                    ->query(fn (Builder $query): Builder => $query
                        ->where('next_follow_up_at', '<', now())
                        ->whereNotIn('status', ['won', 'lost'])),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('convertToCustomer')
                    ->label('Convert to Customer')
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->color('success')
                    ->visible(fn (Lead $record): bool => ! $record->converted_customer_id)
                    ->requiresConfirmation()
                    ->action(function (Lead $record): void {
                        $customer = app(LeadConversionService::class)->convertToCustomer($record);
                        Notification::make()
                            ->title("Lead converted to customer: {$customer->name}")
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
