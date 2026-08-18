<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Meta Ads ad set within a campaign. `product_id` is only set for ad sets
 * created via this app (Phase C/D) so we know which product it promotes —
 * synced-from-Meta ad sets leave it null.
 */
class MetaAdSet extends Model
{
    use BelongsToCompany;

    public const SOURCE_META = 'meta';

    public const SOURCE_ERP = 'erp';

    protected $fillable = [
        'company_id',
        'meta_ad_campaign_id',
        'meta_id',
        'name',
        'status',
        'effective_status',
        'optimization_goal',
        'billing_event',
        'bid_amount',
        'daily_budget',
        'lifetime_budget',
        'targeting',
        'start_time',
        'end_time',
        'product_id',
        'spend',
        'impressions',
        'clicks',
        'ctr',
        'cpc',
        'reach',
        'results',
        'insights_synced_at',
        'source',
    ];

    protected $casts = [
        'bid_amount' => 'decimal:2',
        'daily_budget' => 'decimal:2',
        'lifetime_budget' => 'decimal:2',
        'targeting' => 'array',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'spend' => 'decimal:2',
        'impressions' => 'integer',
        'clicks' => 'integer',
        'ctr' => 'decimal:4',
        'cpc' => 'decimal:4',
        'reach' => 'integer',
        'results' => 'array',
        'insights_synced_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MetaAdCampaign::class, 'meta_ad_campaign_id');
    }

    public function ads(): HasMany
    {
        return $this->hasMany(MetaAd::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'ACTIVE';
    }
}
