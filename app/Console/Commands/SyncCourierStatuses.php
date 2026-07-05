<?php

namespace App\Console\Commands;

use App\Models\CourierBooking;
use App\Models\CourierProvider;
use App\Services\CompanyContext;
use App\Services\CourierAlertService;
use App\Services\CourierManager;
use Illuminate\Console\Command;
use Throwable;

class SyncCourierStatuses extends Command
{
    protected $signature = 'couriers:sync-statuses';

    protected $description = 'Sync active courier bookings with their provider APIs and alert admins on repeated failures or stale bookings';

    public function handle(CourierManager $couriers, CourierAlertService $alerts, CompanyContext $context): int
    {
        $providers = CourierProvider::withoutGlobalScopes()
            ->with('company')
            ->where('is_active', true)
            ->whereIn('driver', CourierProvider::API_DRIVERS)
            ->get()
            ->filter(fn (CourierProvider $provider): bool => filled($provider->credentials));

        $synced = 0;
        $failed = 0;

        foreach ($providers as $provider) {
            $context->set($provider->company);

            try {
                [$providerSynced, $providerFailed] = $this->syncProvider($provider, $couriers, $alerts);
                $synced += $providerSynced;
                $failed += $providerFailed;

                $this->alertStaleBookings($provider, $alerts);
            } finally {
                $context->clear();
            }
        }

        $this->info("Courier bookings synced: {$synced}, failed: {$failed}.");

        return self::SUCCESS;
    }

    /** @return array{0: int, 1: int} */
    protected function syncProvider(CourierProvider $provider, CourierManager $couriers, CourierAlertService $alerts): array
    {
        $cooldown = $provider->monitoringSetting('sync_cooldown_minutes');
        $limit = $provider->monitoringSetting('sync_batch_limit');

        $bookings = CourierBooking::query()
            ->where('courier_provider_id', $provider->id)
            ->whereIn('status', CourierBooking::ACTIVE_STATUSES)
            ->whereNotNull('tracking_id')
            ->where(fn ($query) => $query
                ->whereNull('last_synced_at')
                ->orWhere('last_synced_at', '<=', now()->subMinutes($cooldown)))
            ->orderBy('last_synced_at')
            ->limit($limit)
            ->get();

        $synced = 0;
        $failed = 0;

        foreach ($bookings as $booking) {
            // Stamp before syncing so a booking that keeps throwing cannot
            // hot-loop at the front of every scheduled run.
            $booking->forceFill(['last_synced_at' => now()])->saveQuietly();

            try {
                $couriers->sync($booking);
                $synced++;
                $provider->forceFill([
                    'sync_failure_count' => 0,
                    'last_sync_error' => null,
                    'last_synced_at' => now(),
                ])->saveQuietly();
            } catch (Throwable $exception) {
                $failed++;
                $provider->forceFill([
                    'sync_failure_count' => $provider->sync_failure_count + 1,
                    'last_sync_error' => str($exception->getMessage())->limit(2000),
                    'last_synced_at' => now(),
                ])->saveQuietly();

                if ($provider->sync_failure_count >= $provider->monitoringSetting('sync_failure_alert_threshold')) {
                    $alerts->alert(
                        (int) $provider->company_id,
                        'sync-failure',
                        "provider-{$provider->id}",
                        "Courier sync failing: {$provider->name}",
                        "Status sync for {$provider->name} has failed {$provider->sync_failure_count} times in a row. Last error: ".str($exception->getMessage())->limit(200),
                    );
                }
            }
        }

        return [$synced, $failed];
    }

    protected function alertStaleBookings(CourierProvider $provider, CourierAlertService $alerts): void
    {
        $staleDays = $provider->monitoringSetting('stale_after_days');

        $staleCount = CourierBooking::query()
            ->where('courier_provider_id', $provider->id)
            ->whereIn('status', CourierBooking::ACTIVE_STATUSES)
            ->where('created_at', '<=', now()->subDays($staleDays))
            ->count();

        if ($staleCount === 0) {
            return;
        }

        $alerts->alert(
            (int) $provider->company_id,
            'stale-bookings',
            "provider-{$provider->id}",
            "Stale courier bookings: {$provider->name}",
            "{$staleCount} booking(s) with {$provider->name} have not reached a final status in over {$staleDays} day(s). Review them in Courier Bookings.",
        );
    }
}
