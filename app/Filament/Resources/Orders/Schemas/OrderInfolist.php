<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\CourierBooking;
use App\Models\CustomerRiskProfile;
use App\Models\CustomerRiskReview;
use App\Models\Order;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Invoice')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('order_number')->label('Invoice Number'),
                        TextEntry::make('customer.name')->label('Customer'),
                        TextEntry::make('order_date')->date(),
                        TextEntry::make('source')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => Order::SOURCES[$state ?? Order::SOURCE_ADMIN] ?? str($state)->headline()->toString()),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('delivery_status')
                            ->label('Delivery')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => Order::DELIVERY_STATUSES[$state ?? CourierBooking::STATUS_NOT_BOOKED] ?? str($state)->headline()->toString()),
                    ])
                    ->columns(2),

                Section::make('Courier')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('latestCourierBooking.provider.name')
                            ->label('Provider')
                            ->placeholder('Not booked'),
                        TextEntry::make('latestCourierBooking.tracking_id')
                            ->label('Tracking ID')
                            ->placeholder('Not booked'),
                        TextEntry::make('latestCourierBooking.status')
                            ->label('Courier Status')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => CourierBooking::STATUSES[$state ?? ''] ?? 'Not booked'),
                        TextEntry::make('latestCourierBooking.cod_amount')
                            ->label('COD')
                            ->money('BDT')
                            ->placeholder('BDT 0.00'),
                    ])
                    ->columns(4),

                Section::make('Customer Success & Risk Score')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('latestFraudCheck.risk_score')->label('Score')->placeholder('Not checked'),
                        TextEntry::make('latestFraudCheck.risk_level')->label('Risk Level')->badge()->placeholder('Not checked')
                            ->formatStateUsing(fn (?string $state): string => CustomerRiskProfile::LEVELS[$state ?? ''] ?? 'Not checked'),
                        TextEntry::make('latestFraudCheck.created_at')->label('Checked At')->dateTime()->placeholder('Never'),
                        TextEntry::make('latestRiskReview.status')->label('Review Status')->badge()->placeholder('Not required')
                            ->formatStateUsing(fn (?string $state): string => CustomerRiskReview::STATUSES[$state ?? ''] ?? 'Not required'),
                        TextEntry::make('latestRiskReview.approval_type')->label('Approval Type')->badge()->placeholder('Not required')
                            ->formatStateUsing(fn (?string $state): string => CustomerRiskReview::TYPES[$state ?? ''] ?? 'Not required'),
                        TextEntry::make('latestRiskReview.review_note')->label('Review Note')->placeholder('-'),
                    ])
                    ->columns(3),

                Section::make('Totals')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('subtotal')->money('BDT'),
                        TextEntry::make('discount')->money('BDT'),
                        TextEntry::make('vat')->money('BDT'),
                        TextEntry::make('total_amount')->money('BDT'),
                        TextEntry::make('paid_amount')->money('BDT'),
                        TextEntry::make('due_amount')->money('BDT'),
                    ])
                    ->columns(3),

                Section::make('Items')
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('')
                            ->schema([
                                TextEntry::make('product.name')
                                    ->label('Product'),

                                TextEntry::make('quantity')
                                    ->badge(),

                                TextEntry::make('unit_price')
                                    ->money('BDT'),

                                TextEntry::make('subtotal')
                                    ->money('BDT'),
                            ])
                            ->columns(4)
                            ->contained(false)
                            ->columnSpanFull(),
                    ]),

                TextEntry::make('note')->columnSpanFull(),
            ]);
    }
}
