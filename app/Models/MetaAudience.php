<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Meta Custom Audience (Website or Lookalike subtype only — see the Meta
 * Ads plan's Phase E scope limits), synced from (or, for Lookalikes sourced
 * from our own Website audiences, created via) the Marketing API's
 * `customaudiences` edge. `source` mirrors MetaAdCampaign::$source exactly:
 * 'meta' for audiences that already existed before we started syncing,
 * 'erp' for ones created through this app's New Audience action.
 */
class MetaAudience extends Model
{
    use BelongsToCompany;

    public const SUBTYPE_WEBSITE = 'WEBSITE';

    public const SUBTYPE_LOOKALIKE = 'LOOKALIKE';

    public const SOURCE_META = 'meta';

    public const SOURCE_ERP = 'erp';

    protected $fillable = [
        'company_id',
        'meta_ad_account_id',
        'meta_id',
        'name',
        'subtype',
        'description',
        'pixel_id',
        'retention_days',
        'rule',
        'origin_audience_meta_id',
        'lookalike_country',
        'lookalike_ratio',
        'approximate_count_lower_bound',
        'approximate_count_upper_bound',
        'delivery_status',
        'operation_status',
        'last_synced_at',
        'source',
        'created_by_user_id',
    ];

    protected $casts = [
        'retention_days' => 'integer',
        'rule' => 'array',
        'lookalike_ratio' => 'decimal:2',
        'approximate_count_lower_bound' => 'integer',
        'approximate_count_upper_bound' => 'integer',
        'last_synced_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(MetaAdAccount::class, 'meta_ad_account_id');
    }

    /**
     * Self-relation for Lookalike audiences sourced from one of this
     * account's own Website-subtype audiences — keyed on the Meta object
     * ID (not our local primary key), since that's what Meta itself
     * returns as origin_audience_id.
     */
    public function originAudience(): BelongsTo
    {
        return $this->belongsTo(self::class, 'origin_audience_meta_id', 'meta_id');
    }

    public function approximateSizeLabel(): string
    {
        if ($this->approximate_count_lower_bound === null && $this->approximate_count_upper_bound === null) {
            return 'Building…';
        }

        return number_format((int) $this->approximate_count_lower_bound).' - '.number_format((int) $this->approximate_count_upper_bound);
    }
}
