<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorefrontMetaAttribution extends Model
{
    use BelongsToCompany;

    public const DUE_IMMEDIATE = 'immediate';

    public const DUE_CONFIRMED = 'confirmed';

    protected $fillable = [
        'company_id',
        'order_id',
        'context',
        'purchase_due_stage',
        'consent_granted',
        'recovered',
        'storefront_cart_record_id',
        'purchase_dispatched_at',
        'recovered_dispatched_at',
    ];

    protected $casts = [
        'context' => 'encrypted:array',
        'consent_granted' => 'boolean',
        'recovered' => 'boolean',
        'purchase_dispatched_at' => 'datetime',
        'recovered_dispatched_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function cartRecord(): BelongsTo
    {
        return $this->belongsTo(StorefrontCartRecord::class, 'storefront_cart_record_id');
    }
}
