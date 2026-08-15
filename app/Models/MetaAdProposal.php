<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single AI-drafted ad recommendation (Phase D) — always starts as
 * `status: draft` and is never auto-launched. `is_recommended` marks the
 * top pick from a Generate run; up to 2 alternatives may sit alongside it
 * for comparison. "Review & Launch" hands this off to
 * CreateMetaAdCampaign, which flips it to `launched` and links
 * `meta_ad_campaign_id` only after a real (always-PAUSED) campaign is
 * created — the AI itself never calls the create/activate endpoints.
 */
class MetaAdProposal extends Model
{
    use BelongsToCompany;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_LAUNCHED = 'launched';

    public const STATUS_DISMISSED = 'dismissed';

    protected $fillable = [
        'company_id',
        'meta_ad_account_id',
        'product_id',
        'is_recommended',
        'suggested_budget',
        'suggested_duration_days',
        'headline',
        'primary_text',
        'description_text',
        'call_to_action',
        'targeting',
        'reasoning_text',
        'ai_provider',
        'ai_model',
        'status',
        'meta_ad_campaign_id',
    ];

    protected $casts = [
        'is_recommended' => 'boolean',
        'suggested_budget' => 'decimal:2',
        'suggested_duration_days' => 'integer',
        'targeting' => 'array',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(MetaAdAccount::class, 'meta_ad_account_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MetaAdCampaign::class, 'meta_ad_campaign_id');
    }
}
