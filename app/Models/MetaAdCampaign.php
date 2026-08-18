<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Meta Ads campaign, synced from (or, in a later phase, created via) the
 * Marketing API. `source` distinguishes campaigns that already existed on
 * Meta before we started syncing ('meta') from ones this app created
 * ('erp' — not used until Phase C).
 */
class MetaAdCampaign extends Model
{
    use BelongsToCompany;

    public const SOURCE_META = 'meta';

    public const SOURCE_ERP = 'erp';

    protected $fillable = [
        'company_id',
        'meta_ad_account_id',
        'meta_id',
        'name',
        'objective',
        'status',
        'effective_status',
        'daily_budget',
        'lifetime_budget',
        'buying_type',
        'special_ad_categories',
        'start_time',
        'stop_time',
        'spend',
        'impressions',
        'clicks',
        'ctr',
        'cpc',
        'reach',
        'results',
        'insights_synced_at',
        'source',
        'created_by_user_id',
    ];

    protected $casts = [
        'daily_budget' => 'decimal:2',
        'lifetime_budget' => 'decimal:2',
        'special_ad_categories' => 'array',
        'start_time' => 'datetime',
        'stop_time' => 'datetime',
        'spend' => 'decimal:2',
        'impressions' => 'integer',
        'clicks' => 'integer',
        'ctr' => 'decimal:4',
        'cpc' => 'decimal:4',
        'reach' => 'integer',
        'results' => 'array',
        'insights_synced_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(MetaAdAccount::class, 'meta_ad_account_id');
    }

    public function adSets(): HasMany
    {
        return $this->hasMany(MetaAdSet::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'ACTIVE';
    }
}
