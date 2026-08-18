<?php

namespace App\Services;

use App\Models\MetaAd;
use App\Models\MetaAdAccount;
use App\Models\MetaAdCampaign;
use App\Models\MetaAdSet;
use Illuminate\Support\Arr;
use Throwable;

/**
 * Pulls Campaigns -> Ad Sets -> Ads (with insights) for one Meta Ads
 * account and upserts them locally by `meta_id`, so the dashboard never has
 * to call Meta live on every page load. Mirrors SyncCourierStatuses'
 * failure bookkeeping: a failure here never throws out to the caller — it's
 * stamped on the account (`last_sync_error`/`sync_failure_count`) so one
 * broken account never blocks syncing the rest.
 */
class MetaAdsSyncService
{
    public function __construct(protected MetaMarketingApiClient $client) {}

    /** @return array{campaigns: int, ad_sets: int, ads: int} */
    public function sync(MetaAdAccount $account, string $datePreset = MetaMarketingApiClient::DEFAULT_DATE_PRESET): array
    {
        $counts = ['campaigns' => 0, 'ad_sets' => 0, 'ads' => 0];

        try {
            foreach ($this->client->campaigns($account, $datePreset) as $campaignData) {
                $campaign = $this->upsertCampaign($account, $campaignData);
                $counts['campaigns']++;

                foreach ($this->client->adSets($account, $campaign->meta_id, $datePreset) as $adSetData) {
                    $adSet = $this->upsertAdSet($campaign, $adSetData);
                    $counts['ad_sets']++;

                    foreach ($this->client->ads($account, $adSet->meta_id, $datePreset) as $adData) {
                        $this->upsertAd($adSet, $adData);
                        $counts['ads']++;
                    }
                }
            }

            $account->forceFill([
                'sync_failure_count' => 0,
                'last_sync_error' => null,
                'last_synced_at' => now(),
            ])->save();
        } catch (Throwable $exception) {
            $account->forceFill([
                'sync_failure_count' => $account->sync_failure_count + 1,
                'last_sync_error' => str($exception->getMessage())->limit(2000),
                'last_synced_at' => now(),
            ])->save();
        }

        return $counts;
    }

    protected function upsertCampaign(MetaAdAccount $account, array $data): MetaAdCampaign
    {
        $insights = $this->insightMetrics($data);

        return MetaAdCampaign::query()->updateOrCreate(
            ['meta_ad_account_id' => $account->getKey(), 'meta_id' => $data['id']],
            [
                'company_id' => $account->company_id,
                'name' => $data['name'] ?? '(untitled campaign)',
                'objective' => $data['objective'] ?? null,
                'status' => $data['status'] ?? null,
                'effective_status' => $data['effective_status'] ?? null,
                'daily_budget' => $this->minorToMajor($data['daily_budget'] ?? null),
                'lifetime_budget' => $this->minorToMajor($data['lifetime_budget'] ?? null),
                'buying_type' => $data['buying_type'] ?? null,
                'special_ad_categories' => $data['special_ad_categories'] ?? null,
                'start_time' => $data['start_time'] ?? null,
                'stop_time' => $data['stop_time'] ?? null,
                ...$insights,
                'insights_synced_at' => now(),
            ],
        );
    }

    protected function upsertAdSet(MetaAdCampaign $campaign, array $data): MetaAdSet
    {
        $insights = $this->insightMetrics($data);

        return MetaAdSet::query()->updateOrCreate(
            ['meta_ad_campaign_id' => $campaign->getKey(), 'meta_id' => $data['id']],
            [
                'company_id' => $campaign->company_id,
                'name' => $data['name'] ?? '(untitled ad set)',
                'status' => $data['status'] ?? null,
                'effective_status' => $data['effective_status'] ?? null,
                'optimization_goal' => $data['optimization_goal'] ?? null,
                'billing_event' => $data['billing_event'] ?? null,
                'bid_amount' => $this->minorToMajor($data['bid_amount'] ?? null),
                'daily_budget' => $this->minorToMajor($data['daily_budget'] ?? null),
                'lifetime_budget' => $this->minorToMajor($data['lifetime_budget'] ?? null),
                'targeting' => $data['targeting'] ?? null,
                'start_time' => $data['start_time'] ?? null,
                'end_time' => $data['end_time'] ?? null,
                ...$insights,
                'insights_synced_at' => now(),
            ],
        );
    }

    protected function upsertAd(MetaAdSet $adSet, array $data): MetaAd
    {
        $insights = $this->insightMetrics($data);
        $creative = $data['creative'] ?? [];

        return MetaAd::query()->updateOrCreate(
            ['meta_ad_set_id' => $adSet->getKey(), 'meta_id' => $data['id']],
            [
                'company_id' => $adSet->company_id,
                'name' => $data['name'] ?? '(untitled ad)',
                'status' => $data['status'] ?? null,
                'effective_status' => $data['effective_status'] ?? null,
                'meta_creative_id' => $creative['id'] ?? null,
                'headline' => $creative['title'] ?? null,
                'primary_text' => $creative['body'] ?? null,
                'image_hash' => $creative['image_hash'] ?? null,
                ...$insights,
                'insights_synced_at' => now(),
            ],
        );
    }

    /**
     * Meta returns budgets in the account's minor currency unit (cents).
     * Everything we store locally is in major units to match every other
     * money column in this app (see StockMovementService's own precedent
     * of normalizing external shapes into our own conventions).
     */
    protected function minorToMajor(mixed $value): ?float
    {
        return is_numeric($value) ? round(((float) $value) / 100, 2) : null;
    }

    /** @return array{spend: float, impressions: int, clicks: int, ctr: float, cpc: float, reach: int, results: array|null} */
    protected function insightMetrics(array $data): array
    {
        $row = Arr::first(Arr::get($data, 'insights.data', []), default: []);

        return [
            'spend' => (float) ($row['spend'] ?? 0),
            'impressions' => (int) ($row['impressions'] ?? 0),
            'clicks' => (int) ($row['clicks'] ?? 0),
            'ctr' => (float) ($row['ctr'] ?? 0),
            'cpc' => (float) ($row['cpc'] ?? 0),
            'reach' => (int) ($row['reach'] ?? 0),
            'results' => $row['actions'] ?? null,
        ];
    }
}
