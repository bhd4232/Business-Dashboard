<?php

namespace App\Filament\Widgets;

use App\Models\CourierBooking;
use App\Models\CourierProvider;
use App\Models\CourierWebhookLog;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class CourierHealthWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'Courier Health';

    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        return Schema::hasTable('courier_bookings')
            && Schema::hasColumn('courier_providers', 'sync_failure_count')
            && (Auth::user()?->hasPermission('sales.view') ?? false);
    }

    protected function getStats(): array
    {
        $active = CourierBooking::query()
            ->whereIn('status', CourierBooking::ACTIVE_STATUSES)
            ->count();

        $stale = CourierBooking::query()
            ->whereIn('status', CourierBooking::ACTIVE_STATUSES)
            ->where('created_at', '<=', now()->subDays(CourierProvider::MONITORING_DEFAULTS['stale_after_days']))
            ->count();

        $failedWebhooks = CourierWebhookLog::query()
            ->where('status', 'failed')
            ->where('updated_at', '>=', now()->subDay())
            ->count();

        $failingProviders = CourierProvider::query()
            ->where('is_active', true)
            ->where('sync_failure_count', '>', 0)
            ->count();

        return [
            Stat::make('Active Deliveries', $active)
                ->description('Bookings awaiting a final courier status')
                ->icon(Heroicon::OutlinedTruck)
                ->color('info'),
            Stat::make('Stale Bookings', $stale)
                ->description($stale > 0 ? 'No final status for days — follow up' : 'Nothing stuck')
                ->descriptionIcon($stale > 0 ? Heroicon::OutlinedExclamationTriangle : Heroicon::OutlinedCheckCircle)
                ->icon(Heroicon::OutlinedClock)
                ->color($stale > 0 ? 'warning' : 'success'),
            Stat::make('Failed Webhooks (24h)', $failedWebhooks)
                ->description($failedWebhooks > 0 ? 'Check Courier Webhook Logs' : 'All webhooks processed')
                ->descriptionIcon($failedWebhooks > 0 ? Heroicon::OutlinedExclamationTriangle : Heroicon::OutlinedCheckCircle)
                ->icon(Heroicon::OutlinedBolt)
                ->color($failedWebhooks > 0 ? 'danger' : 'success'),
            Stat::make('Providers With Sync Errors', $failingProviders)
                ->description($failingProviders > 0 ? 'Status sync is failing — check credentials' : 'All providers syncing')
                ->descriptionIcon($failingProviders > 0 ? Heroicon::OutlinedExclamationTriangle : Heroicon::OutlinedCheckCircle)
                ->icon(Heroicon::OutlinedSignal)
                ->color($failingProviders > 0 ? 'danger' : 'success'),
        ];
    }
}
