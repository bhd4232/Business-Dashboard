<?php

namespace App\Filament\Resources\Quotations\Tables;

use App\Models\Quotation;
use App\Services\Crm\LeadConversionService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class QuotationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('quotation_number')->label('Quotation #')->searchable()->sortable(),
                TextColumn::make('lead.name')->label('Lead')->placeholder('-')->searchable(),
                TextColumn::make('customer.name')->label('Customer')->placeholder('-')->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'sent' => 'info',
                        'accepted' => 'success',
                        'rejected', 'expired' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('total_amount')->money('BDT')->sortable(),
                TextColumn::make('valid_until')->date()->placeholder('-')->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options(Quotation::STATUSES),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('markSent')
                    ->label('Mark Sent')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->color('info')
                    ->visible(fn (Quotation $record): bool => $record->status === 'draft')
                    ->requiresConfirmation()
                    ->action(fn (Quotation $record) => $record->update(['status' => 'sent'])),
                Action::make('markAccepted')
                    ->label('Mark Accepted')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->visible(fn (Quotation $record): bool => $record->status === 'sent')
                    ->requiresConfirmation()
                    ->action(fn (Quotation $record) => $record->update(['status' => 'accepted'])),
                Action::make('markRejected')
                    ->label('Mark Rejected')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->visible(fn (Quotation $record): bool => in_array($record->status, ['sent', 'accepted'], true) && ! $record->converted_order_id)
                    ->requiresConfirmation()
                    ->action(fn (Quotation $record) => $record->update(['status' => 'rejected'])),
                Action::make('convertToOrder')
                    ->label('Convert to Order')
                    ->icon(Heroicon::OutlinedShoppingCart)
                    ->color('success')
                    ->visible(fn (Quotation $record): bool => $record->status === 'accepted' && ! $record->converted_order_id)
                    ->requiresConfirmation()
                    ->action(function (Quotation $record): void {
                        $order = app(LeadConversionService::class)->convertQuotationToOrder($record);
                        Notification::make()
                            ->title("Order {$order->order_number} created from quotation.")
                            ->success()
                            ->send();
                    }),
                Action::make('publicLink')
                    ->label('Public Link')
                    ->icon(Heroicon::OutlinedLink)
                    ->url(fn (Quotation $record): string => route('quotation.public', $record->quotation_number))
                    ->openUrlInNewTab(),
                Action::make('shareWhatsApp')
                    ->label('Share on WhatsApp')
                    ->icon(Heroicon::OutlinedShare)
                    ->url(function (Quotation $record): string {
                        $link = route('quotation.public', $record->quotation_number);
                        $text = "আপনার কোটেশন দেখুন: {$link}";
                        $phone = preg_replace('/\D/', '', $record->customer?->phone ?? $record->lead?->phone ?? '');
                        $phone = str_starts_with($phone, '0') ? '88'.$phone : $phone;

                        return "https://wa.me/{$phone}?text=".urlencode($text);
                    })
                    ->visible(fn (Quotation $record): bool => filled($record->customer?->phone ?? $record->lead?->phone))
                    ->openUrlInNewTab(),
            ]);
    }
}
