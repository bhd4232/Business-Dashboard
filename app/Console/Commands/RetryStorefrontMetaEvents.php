<?php

namespace App\Console\Commands;

use App\Jobs\RetryStorefrontMetaEventJob;
use App\Models\StorefrontMetaAttribution;
use App\Models\StorefrontMetaEvent;
use Illuminate\Console\Command;

class RetryStorefrontMetaEvents extends Command
{
    protected $signature = 'storefront:retry-meta-events {--limit=100}';

    protected $description = 'Queue bounded retries for failed storefront Meta events';

    public function handle(): int
    {
        $limit = max(1, min(500, (int) $this->option('limit')));
        $events = StorefrontMetaEvent::withoutGlobalScopes()
            ->where('status', StorefrontMetaEvent::STATUS_FAILED)
            ->where('attempts', '<', 5)
            ->where('updated_at', '<=', now()->subMinutes(10))
            ->oldest('updated_at')
            ->limit($limit)
            ->pluck('id');

        foreach ($events as $eventId) {
            RetryStorefrontMetaEventJob::dispatch((int) $eventId);
        }

        $expiredAttributions = StorefrontMetaAttribution::withoutGlobalScopes()
            ->whereNotNull('context')
            ->where('updated_at', '<', now()->subDays(30))
            ->update(['context' => null]);

        $this->info("Meta event retries queued: {$events->count()}.");
        $this->info("Expired Meta attribution contexts cleared: {$expiredAttributions}.");

        return self::SUCCESS;
    }
}
