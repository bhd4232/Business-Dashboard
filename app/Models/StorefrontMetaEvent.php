<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorefrontMetaEvent extends Model
{
    use BelongsToCompany;

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'company_id',
        'order_id',
        'pixel_id',
        'order_status_transition_id',
        'storefront_cart_record_id',
        'event_name',
        'event_id',
        'status',
        'attempts',
        'payload_summary',
        'response_summary',
        'error',
        'sent_at',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'payload_summary' => 'array',
        'response_summary' => 'array',
        'sent_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderStatusTransition(): BelongsTo
    {
        return $this->belongsTo(OrderStatusTransition::class);
    }

    public function cartRecord(): BelongsTo
    {
        return $this->belongsTo(StorefrontCartRecord::class, 'storefront_cart_record_id');
    }
}
