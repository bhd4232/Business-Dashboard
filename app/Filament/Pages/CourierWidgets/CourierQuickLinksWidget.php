<?php

namespace App\Filament\Pages\CourierWidgets;

use App\Filament\Pages\CourierPaymentHistory;
use App\Filament\Resources\CourierBookings\CourierBookingResource;
use App\Filament\Resources\CourierReturns\CourierReturnResource;
use App\Filament\Resources\CourierStatusLogs\CourierStatusLogResource;
use App\Filament\Resources\CourierWebhookLogs\CourierWebhookLogResource;
use App\Models\CourierBooking;
use App\Models\CourierReturn;
use App\Models\CourierStatusLog;
use App\Models\CourierWebhookLog;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Card-grid quick links to the Bookings/Status Logs/Returns/Webhook
 * Logs/Payments screens, using Filament's native Stat card component so
 * they render with the panel's standard widget styling. Deliberately kept
 * outside app/Filament/Widgets (which is auto-discovered and shown on the
 * main admin Dashboard) — this widget is embedded directly into the
 * Courier Merchant Dashboard view only.
 */
class CourierQuickLinksWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $failedWebhooks = CourierWebhookLog::query()->where('status', 'failed')->count();

        return [
            Stat::make('Bookings', number_format(CourierBooking::query()->count()))
                ->description('Every consignment booked with a courier')
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->color('primary')
                ->url(CourierBookingResource::getUrl()),

            Stat::make('Status Logs', number_format(CourierStatusLog::query()->count()))
                ->description('Full history of every status change')
                ->icon(Heroicon::OutlinedListBullet)
                ->color('gray')
                ->url(CourierStatusLogResource::getUrl()),

            Stat::make('Returns', number_format(CourierReturn::query()->count()))
                ->description('Return requests sent to the courier')
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('warning')
                ->url(CourierReturnResource::getUrl()),

            Stat::make('Webhook Logs', number_format($failedWebhooks))
                ->description($failedWebhooks > 0 ? 'Failed webhooks — needs attention' : 'Incoming status webhooks')
                ->icon(Heroicon::OutlinedSignal)
                ->color($failedWebhooks > 0 ? 'danger' : 'gray')
                ->url(CourierWebhookLogResource::getUrl()),

            Stat::make('Payments', 'View')
                ->description('Steadfast payment / settlement history')
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('success')
                ->url(CourierPaymentHistory::getUrl()),
        ];
    }
}
