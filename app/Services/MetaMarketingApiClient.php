<?php

namespace App\Services;

use App\Models\MetaAdAccount;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Thin wrapper over the Meta Marketing API (Graph API). Mirrors
 * SteadfastCourierClient's shape: one method per real Graph API endpoint,
 * blank-credential guard, `Http::...->throw()->json()`.
 *
 * Each list endpoint asks Meta to nest the `insights` edge directly on the
 * object fields (`insights.date_preset(last_30d){...}`) so one call returns
 * both the object and its performance numbers — avoids an extra round trip
 * per campaign/ad set/ad. `date_preset` accepts Meta's own real presets
 * (today, yesterday, last_7d, last_30d, this_month, last_month, ...); we
 * never invent our own date-range semantics here.
 *
 * Pagination: only the first page (`limit` below) is fetched. Typical SMB
 * catalogs stay well under this; following `paging.next` fully is deferred
 * until an account is observed to exceed it.
 *
 * Phase B added the two write-back calls (`updateStatus()`/`updateBudget()`)
 * used by the dashboard's Pause/Resume action and inline budget edit.
 *
 * Phase C adds real object *creation* (`createCampaign()`/`createAdSet()`/
 * `uploadAdImage()`/`createAdCreative()`/`createAd()`, orchestrated by
 * MetaAdsCreationService). Every create call hard-codes `status: PAUSED` —
 * the plan's core safety rule — so nothing built here can ever start
 * spending without a later, separate, explicit Resume (Phase B). Complex
 * params (`targeting`, `object_story_spec`, `creative`,
 * `special_ad_categories`) are JSON-encoded before sending, matching
 * Meta's documented convention for array/object-valued form params.
 * Deliberately scoped to one objective (`OUTCOME_TRAFFIC` /
 * `LINK_CLICKS` / `IMPRESSIONS`) — see the Meta Ads plan for why.
 *
 * Phase E adds Pixel diagnostics (`pixel()`/`pixelEventStats()`) and Custom
 * Audience management (`customAudiences()`/`createWebsiteCustomAudience()`/
 * `createLookalikeAudience()`/`deleteAudience()`), orchestrated by
 * MetaAudienceSyncService and the Events Manager page. Scoped to Website and
 * Lookalike audiences only — see the Meta Ads plan for why "Customer List"
 * (bulk hashed-PII upload) audiences are deliberately out of scope.
 */
class MetaMarketingApiClient
{
    public const DEFAULT_DATE_PRESET = 'last_30d';

    public const STATUS_ACTIVE = 'ACTIVE';

    public const STATUS_PAUSED = 'PAUSED';

    /** The only campaign objective Phase C supports — see the class docblock. */
    public const OBJECTIVE_TRAFFIC = 'OUTCOME_TRAFFIC';

    protected const OPTIMIZATION_GOAL = 'LINK_CLICKS';

    protected const BILLING_EVENT = 'IMPRESSIONS';

    protected const PAGE_LIMIT = 200;

    protected const CAMPAIGN_FIELDS = 'id,name,objective,status,effective_status,daily_budget,lifetime_budget,buying_type,special_ad_categories,start_time,stop_time';

    protected const AD_SET_FIELDS = 'id,name,status,effective_status,optimization_goal,billing_event,bid_amount,daily_budget,lifetime_budget,targeting,start_time,end_time';

    protected const AD_FIELDS = 'id,name,status,effective_status,creative{id,name,title,body,image_hash,object_story_spec}';

    protected const INSIGHT_FIELDS = 'spend,impressions,clicks,ctr,cpc,reach,actions';

    protected const AUDIENCE_FIELDS = 'id,name,subtype,description,approximate_count_lower_bound,approximate_count_upper_bound,delivery_status,operation_status,time_created,time_updated';

    public function campaigns(MetaAdAccount $account, string $datePreset = self::DEFAULT_DATE_PRESET): array
    {
        return $this->request($account)
            ->get($this->baseUrl().'/act_'.$account->adAccountId().'/campaigns', [
                'fields' => self::CAMPAIGN_FIELDS.$this->insightsField($datePreset),
                'limit' => self::PAGE_LIMIT,
                'access_token' => $account->accessToken(),
            ])
            ->throw()
            ->json('data', []);
    }

    public function adSets(MetaAdAccount $account, string $campaignMetaId, string $datePreset = self::DEFAULT_DATE_PRESET): array
    {
        return $this->request($account)
            ->get($this->baseUrl().'/'.$campaignMetaId.'/adsets', [
                'fields' => self::AD_SET_FIELDS.$this->insightsField($datePreset),
                'limit' => self::PAGE_LIMIT,
                'access_token' => $account->accessToken(),
            ])
            ->throw()
            ->json('data', []);
    }

    public function ads(MetaAdAccount $account, string $adSetMetaId, string $datePreset = self::DEFAULT_DATE_PRESET): array
    {
        return $this->request($account)
            ->get($this->baseUrl().'/'.$adSetMetaId.'/ads', [
                'fields' => self::AD_FIELDS.$this->insightsField($datePreset),
                'limit' => self::PAGE_LIMIT,
                'access_token' => $account->accessToken(),
            ])
            ->throw()
            ->json('data', []);
    }

    /**
     * Used by the "Test Connection" action on the account settings screen —
     * a cheap call that fails clearly when the App ID/Secret/Token/Ad
     * Account ID combination is wrong, without touching any campaign data.
     */
    public function verifyCredentials(MetaAdAccount $account): array
    {
        return $this->request($account)
            ->get($this->baseUrl().'/act_'.$account->adAccountId(), [
                'fields' => 'name,currency,account_status',
                'access_token' => $account->accessToken(),
            ])
            ->throw()
            ->json();
    }

    /**
     * Pause/resume a campaign, ad set, or ad — the same endpoint and
     * `status` param works for all three object types. Meta treats this as
     * idempotent, so the client's own retry-on-failure is safe here.
     */
    public function updateStatus(MetaAdAccount $account, string $objectId, string $status): array
    {
        return $this->write($account, '/'.$objectId, ['status' => $status]);
    }

    /**
     * Campaign/ad-set budget only — ads have no budget of their own. Meta's
     * API takes budgets in the account's minor currency unit (cents); the
     * caller passes major units (e.g. 25.00), converted here — the mirror
     * image of MetaAdsSyncService::minorToMajor().
     *
     * @param  array{daily_budget?: float, lifetime_budget?: float}  $budget
     */
    public function updateBudget(MetaAdAccount $account, string $objectId, array $budget): array
    {
        $payload = [];

        foreach (['daily_budget', 'lifetime_budget'] as $key) {
            if (array_key_exists($key, $budget)) {
                $payload[$key] = (int) round(((float) $budget[$key]) * 100);
            }
        }

        return $this->write($account, '/'.$objectId, $payload);
    }

    /**
     * Always created PAUSED regardless of anything the caller passes —
     * the plan's core safety rule. `special_ad_categories` is required by
     * Meta even when empty (the "Special Ad Category" housing/employment/
     * credit regulation), so `[]` is always sent, never omitted.
     */
    public function createCampaign(MetaAdAccount $account, string $name): array
    {
        return $this->write($account, '/act_'.$account->adAccountId().'/campaigns', [
            'name' => $name,
            'objective' => self::OBJECTIVE_TRAFFIC,
            'special_ad_categories' => json_encode([]),
            'status' => self::STATUS_PAUSED,
        ]);
    }

    /**
     * @param  array{geo_locations: array, age_min: int, age_max: int, genders?: array<int>}  $targeting
     */
    public function createAdSet(MetaAdAccount $account, string $campaignMetaId, string $name, float $dailyBudget, array $targeting): array
    {
        return $this->write($account, '/act_'.$account->adAccountId().'/adsets', [
            'name' => $name,
            'campaign_id' => $campaignMetaId,
            'daily_budget' => (int) round($dailyBudget * 100),
            'billing_event' => self::BILLING_EVENT,
            'optimization_goal' => self::OPTIMIZATION_GOAL,
            'targeting' => json_encode($targeting),
            'status' => self::STATUS_PAUSED,
        ]);
    }

    /**
     * Uploads raw image bytes (never a public-URL fetch — works regardless
     * of whether the storefront is publicly reachable) and returns the
     * image hash `createAdCreative()` needs.
     */
    public function uploadAdImage(MetaAdAccount $account, string $bytes): string
    {
        $response = $this->write($account, '/act_'.$account->adAccountId().'/adimages', [
            'bytes' => base64_encode($bytes),
        ]);

        $image = Arr::first($response['images'] ?? []);
        $hash = $image['hash'] ?? null;

        if (blank($hash)) {
            throw new RuntimeException('Meta did not return an image hash for the uploaded image.');
        }

        return $hash;
    }

    /**
     * @param  array{page_id: string, link_data: array}  $objectStorySpec
     */
    public function createAdCreative(MetaAdAccount $account, string $name, array $objectStorySpec): array
    {
        return $this->write($account, '/act_'.$account->adAccountId().'/adcreatives', [
            'name' => $name,
            'object_story_spec' => json_encode($objectStorySpec),
        ]);
    }

    public function createAd(MetaAdAccount $account, string $adSetMetaId, string $name, string $creativeId): array
    {
        return $this->write($account, '/act_'.$account->adAccountId().'/ads', [
            'name' => $name,
            'adset_id' => $adSetMetaId,
            'creative' => json_encode(['creative_id' => $creativeId]),
            'status' => self::STATUS_PAUSED,
        ]);
    }

    /**
     * Basic Pixel identity/health — used by the Events Manager page's Pixel
     * Health panel. Reliable, well-documented fields.
     */
    public function pixel(MetaAdAccount $account, string $pixelId): array
    {
        return $this->request($account)
            ->get($this->baseUrl().'/'.$pixelId, [
                'fields' => 'id,name,creation_time,last_fired_time,is_unavailable',
                'access_token' => $account->accessToken(),
            ])
            ->throw()
            ->json();
    }

    /**
     * Real event-volume-by-type for a Pixel over a time window. Meta's
     * public docs do not fully specify this endpoint's response row shape,
     * so callers must parse the returned rows defensively rather than
     * assuming fixed keys — see MetaEventsManager::loadPixelHealth().
     */
    public function pixelEventStats(MetaAdAccount $account, string $pixelId, int $startTime, int $endTime): array
    {
        return $this->request($account)
            ->get($this->baseUrl().'/'.$pixelId.'/stats', [
                'aggregation' => 'event',
                'start_time' => $startTime,
                'end_time' => $endTime,
                'access_token' => $account->accessToken(),
            ])
            ->throw()
            ->json('data', []);
    }

    /**
     * Website + Lookalike Custom Audiences for this ad account — "Customer
     * List" (bulk hashed-PII upload) audiences are deliberately out of
     * scope, so no `customer_file_source` handling exists here.
     */
    public function customAudiences(MetaAdAccount $account): array
    {
        return $this->request($account)
            ->get($this->baseUrl().'/act_'.$account->adAccountId().'/customaudiences', [
                'fields' => self::AUDIENCE_FIELDS,
                'limit' => self::PAGE_LIMIT,
                'access_token' => $account->accessToken(),
            ])
            ->throw()
            ->json('data', []);
    }

    /**
     * Pure rule-builder, no HTTP — shared by createWebsiteCustomAudience()
     * (JSON-encoded into the create request) and the Events Manager page's
     * New Audience action (persisted verbatim into MetaAudience::$rule), so
     * the stored record and the request actually sent can never drift
     * apart.
     *
     * @return array<string, mixed>
     */
    public function websiteAudienceRule(string $pixelId, int $retentionDays, ?string $urlContains = null): array
    {
        $rule = [
            'event_sources' => [
                ['id' => $pixelId, 'type' => 'pixel'],
            ],
            'retention_seconds' => $retentionDays * 86400,
        ];

        if (filled($urlContains)) {
            $rule['filter'] = [
                'operator' => 'and',
                'filters' => [
                    ['field' => 'url', 'operator' => 'i_contains', 'value' => $urlContains],
                ],
            ];
        }

        return [
            'inclusions' => [
                'operator' => 'or',
                'rules' => [$rule],
            ],
        ];
    }

    public function createWebsiteCustomAudience(MetaAdAccount $account, string $name, string $pixelId, int $retentionDays, ?string $urlContains = null): array
    {
        return $this->write($account, '/act_'.$account->adAccountId().'/customaudiences', [
            'name' => $name,
            'subtype' => 'WEBSITE',
            'rule' => json_encode($this->websiteAudienceRule($pixelId, $retentionDays, $urlContains)),
        ]);
    }

    /**
     * @param  float  $ratioPercent  1-20, converted to Meta's 0-1 ratio here.
     */
    public function createLookalikeAudience(MetaAdAccount $account, string $name, string $originAudienceMetaId, string $country, float $ratioPercent): array
    {
        return $this->write($account, '/act_'.$account->adAccountId().'/customaudiences', [
            'name' => $name,
            'subtype' => 'LOOKALIKE',
            'origin_audience_id' => $originAudienceMetaId,
            'lookalike_spec' => json_encode([
                'type' => 'similarity',
                'country' => $country,
                'ratio' => $ratioPercent / 100,
            ]),
        ]);
    }

    public function deleteAudience(MetaAdAccount $account, string $audienceMetaId): array
    {
        return $this->request($account)
            ->asForm()
            ->delete($this->baseUrl().'/'.$audienceMetaId, [
                'access_token' => $account->accessToken(),
            ])
            ->throw()
            ->json();
    }

    protected function write(MetaAdAccount $account, string $path, array $payload): array
    {
        return $this->request($account)
            ->asForm()
            ->post($this->baseUrl().$path, [...$payload, 'access_token' => $account->accessToken()])
            ->throw()
            ->json();
    }

    protected function insightsField(string $datePreset): string
    {
        return ',insights.date_preset('.$datePreset.'){'.self::INSIGHT_FIELDS.'}';
    }

    protected function request(MetaAdAccount $account): PendingRequest
    {
        if (! $account->hasCredentials()) {
            throw ValidationException::withMessages([
                'credentials' => 'A Meta access token and ad account ID are required.',
            ]);
        }

        return Http::acceptJson()
            ->timeout(20)
            ->connectTimeout(5)
            ->retry(2, 500, throw: false);
    }

    protected function baseUrl(): string
    {
        $version = config('services.meta.graph_api_version', 'v25.0');

        return 'https://graph.facebook.com/'.$version;
    }
}
