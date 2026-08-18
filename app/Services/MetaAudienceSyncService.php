<?php

namespace App\Services;

use App\Models\MetaAdAccount;
use App\Models\MetaAudience;

/**
 * Pulls this account's Custom Audiences from Meta and upserts them locally
 * by `meta_id`, mirroring MetaAdsSyncService's upsert style. Unlike that
 * service, a failure here is deliberately left to propagate — there is no
 * per-audience-sync failure column on MetaAdAccount, and reusing its
 * campaign-sync `last_sync_error` would wrongly conflate two independent
 * sync loops. The caller (MetaEventsManager's "Sync Audiences" action) is
 * responsible for catching and reporting.
 *
 * The upsert's value set is deliberately narrow: it never touches `source`,
 * `pixel_id`, `retention_days`, `rule`, `origin_audience_meta_id`,
 * `lookalike_country`, `lookalike_ratio`, or `created_by_user_id`, so a
 * periodic sync can never clobber what the New Audience action set at
 * creation time — the same discipline MetaAdsSyncService::upsertCampaign()/
 * upsertAdSet() already use for their own `source`/`product_id` columns.
 */
class MetaAudienceSyncService
{
    public function __construct(protected MetaMarketingApiClient $client) {}

    /** @return array{created: int, updated: int} */
    public function sync(MetaAdAccount $account): array
    {
        $counts = ['created' => 0, 'updated' => 0];

        foreach ($this->client->customAudiences($account) as $data) {
            $existed = MetaAudience::query()
                ->where('meta_ad_account_id', $account->getKey())
                ->where('meta_id', $data['id'])
                ->exists();

            MetaAudience::query()->updateOrCreate(
                ['meta_ad_account_id' => $account->getKey(), 'meta_id' => $data['id']],
                [
                    'company_id' => $account->company_id,
                    'name' => $data['name'] ?? '(untitled audience)',
                    'subtype' => $data['subtype'] ?? MetaAudience::SUBTYPE_WEBSITE,
                    'description' => $data['description'] ?? null,
                    'approximate_count_lower_bound' => $data['approximate_count_lower_bound'] ?? null,
                    'approximate_count_upper_bound' => $data['approximate_count_upper_bound'] ?? null,
                    'delivery_status' => $this->statusLabel($data['delivery_status'] ?? null),
                    'operation_status' => $this->statusLabel($data['operation_status'] ?? null),
                    'last_synced_at' => now(),
                ],
            );

            $counts[$existed ? 'updated' : 'created']++;
        }

        return $counts;
    }

    /**
     * Meta returns delivery_status/operation_status as {code, description}
     * objects, not plain strings — pull out something displayable rather
     * than assigning the raw array into a plain string column.
     */
    protected function statusLabel(mixed $status): ?string
    {
        if (is_array($status)) {
            return $status['description'] ?? $status['code'] ?? null;
        }

        return is_string($status) ? $status : null;
    }
}
