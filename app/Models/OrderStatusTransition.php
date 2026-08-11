<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Services\StorefrontMetaDispatchService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class OrderStatusTransition extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'order_id',
        'changed_by',
        'stage',
        'from_status',
        'to_status',
        'from_delivery_status',
        'to_delivery_status',
        'source',
        'reason',
    ];

    protected static function booted(): void
    {
        static::created(function (OrderStatusTransition $transition): void {
            if (! Schema::hasColumn('storefront_settings', 'meta_status_events_enabled')) {
                return;
            }

            app(StorefrontMetaDispatchService::class)->dispatchForTransition($transition);
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
