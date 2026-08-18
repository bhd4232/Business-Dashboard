<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single Meta ad within an ad set. `product_id`/creative fields are only
 * populated for ads created via this app (Phase C/D).
 */
class MetaAd extends Model
{
    use BelongsToCompany;

    public const SOURCE_META = 'meta';

    public const SOURCE_ERP = 'erp';

    protected $fillable = [
        'company_id',
        'meta_ad_set_id',
        'meta_id',
        'name',
        'status',
        'effective_status',
        'meta_creative_id',
        'headline',
        'primary_text',
        'description_text',
        'call_to_action',
        'destination_url',
        'image_hash',
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
        'spend' => 'decimal:2',
        'impressions' => 'integer',
        'clicks' => 'integer',
        'ctr' => 'decimal:4',
        'cpc' => 'decimal:4',
        'reach' => 'integer',
        'results' => 'array',
        'insights_synced_at' => 'datetime',
    ];

    public function adSet(): BelongsTo
    {
        return $this->belongsTo(MetaAdSet::class, 'meta_ad_set_id');
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
