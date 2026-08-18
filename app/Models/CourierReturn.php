<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourierReturn extends Model
{
    use BelongsToCompany;

    public const STATUS_REQUESTED = 'requested';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_COMPLETED = 'completed';

    public const STATUSES = [
        self::STATUS_REQUESTED => 'Requested',
        self::STATUS_APPROVED => 'Approved',
        self::STATUS_REJECTED => 'Rejected',
        self::STATUS_COMPLETED => 'Completed',
    ];

    protected $fillable = [
        'company_id',
        'courier_provider_id',
        'courier_booking_id',
        'provider_reference',
        'reason',
        'status',
        'requested_at',
        'resolved_at',
        'note',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (CourierReturn $return): void {
            $return->status ??= self::STATUS_REQUESTED;
            $return->requested_at ??= now();
        });
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(CourierProvider::class, 'courier_provider_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(CourierBooking::class, 'courier_booking_id');
    }
}
