<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A connected Meta (Facebook) Ads account — App ID/Secret/Access Token/Ad
 * Account ID entered by the owner, mirroring CourierProvider's
 * encrypted-credentials shape (see CourierProvider::$casts). Reachable via
 * MetaMarketingApiClient, kept in sync locally by MetaAdsSyncService /
 * `meta-ads:sync`.
 */
class MetaAdAccount extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'name',
        'credentials',
        'currency',
        'exchange_rate_to_company_currency',
        'is_active',
        'is_default',
        'token_expires_at',
        'last_synced_at',
        'last_sync_error',
        'sync_failure_count',
        'ai_daily_budget_min',
        'ai_daily_budget_max',
        'ai_max_duration_days',
    ];

    protected $hidden = [
        'credentials',
    ];

    protected $casts = [
        'credentials' => 'encrypted:array',
        'exchange_rate_to_company_currency' => 'decimal:4',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'token_expires_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'sync_failure_count' => 'integer',
        'ai_daily_budget_min' => 'decimal:2',
        'ai_daily_budget_max' => 'decimal:2',
        'ai_max_duration_days' => 'integer',
    ];

    protected static function booted(): void
    {
        // Only one account per company may be the default at a time — fires
        // after save so it can safely exclude the just-saved row, same
        // pattern as CourierProvider::booted().
        static::saved(function (MetaAdAccount $account): void {
            if ($account->is_default) {
                static::withoutGlobalScopes()
                    ->where('company_id', $account->company_id)
                    ->whereKeyNot($account->getKey())
                    ->update(['is_default' => false]);
            }
        });
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(MetaAdCampaign::class);
    }

    public function appId(): ?string
    {
        return $this->credentials['app_id'] ?? null;
    }

    public function appSecret(): ?string
    {
        return $this->credentials['app_secret'] ?? null;
    }

    public function accessToken(): ?string
    {
        return $this->credentials['access_token'] ?? null;
    }

    /**
     * Always without the "act_" prefix Meta's own UI shows/accepts — the
     * client builds "/act_{id}" itself, and owners often paste the ID with
     * the prefix already on it, so it's stripped defensively here rather
     * than relying on the form to clean it up.
     */
    public function adAccountId(): ?string
    {
        $id = $this->credentials['ad_account_id'] ?? null;

        return filled($id) ? preg_replace('/^act_/', '', (string) $id) : null;
    }

    public function hasCredentials(): bool
    {
        return filled($this->accessToken()) && filled($this->adAccountId());
    }

    /**
     * Only required for Phase C's create flow (an ad creative's
     * object_story_spec must attach to a real Facebook Page) — not
     * required for Phase A/B, so accounts set up before Phase C keep
     * syncing/managing without it.
     */
    public function pageId(): ?string
    {
        return $this->credentials['page_id'] ?? null;
    }

    public function canCreateAds(): bool
    {
        return $this->hasCredentials() && filled($this->pageId());
    }

    /**
     * Phase D's AI Marketing Assistant is off for this account until the
     * owner sets a real spending ceiling — there is no invented default
     * budget cap.
     */
    public function aiGuardrailsConfigured(): bool
    {
        return filled($this->ai_daily_budget_max);
    }
}
