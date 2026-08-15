<?php

namespace App\Console\Commands;

use App\Models\MetaAdAccount;
use App\Services\CompanyContext;
use App\Services\MetaAdsSyncService;
use Illuminate\Console\Command;

/**
 * Refreshes every active Meta Ads account's local Campaign/Ad Set/Ad cache.
 * Same per-company loop shape as SyncCourierStatuses: each account gets its
 * own CompanyContext and its own try/catch (handled inside
 * MetaAdsSyncService::sync()) so one failing account never blocks the rest.
 * Not wired into a Laravel Schedule — this repo has none; invoke it the
 * same external way `couriers:sync-statuses` is invoked.
 */
class SyncMetaAdsData extends Command
{
    protected $signature = 'meta-ads:sync';

    protected $description = 'Sync every active Meta Ads account with the Marketing API';

    public function handle(MetaAdsSyncService $syncService, CompanyContext $context): int
    {
        $accounts = MetaAdAccount::withoutGlobalScopes()
            ->with('company')
            ->where('is_active', true)
            ->get()
            ->filter(fn (MetaAdAccount $account): bool => $account->hasCredentials());

        $totals = ['campaigns' => 0, 'ad_sets' => 0, 'ads' => 0];
        $failed = 0;

        foreach ($accounts as $account) {
            $context->set($account->company);

            try {
                $result = $syncService->sync($account);
                $totals['campaigns'] += $result['campaigns'];
                $totals['ad_sets'] += $result['ad_sets'];
                $totals['ads'] += $result['ads'];

                if ($account->fresh()->last_sync_error) {
                    $failed++;
                }
            } finally {
                $context->clear();
            }
        }

        $this->info("Meta Ads synced: {$totals['campaigns']} campaigns, {$totals['ad_sets']} ad sets, {$totals['ads']} ads across ".$accounts->count()." account(s); {$failed} failed.");

        return self::SUCCESS;
    }
}
