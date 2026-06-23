<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourierBooking extends Model
{
    use BelongsToCompany;

    public const STATUS_NOT_BOOKED = 'not_booked';

    public const STATUS_BOOKING_PENDING = 'booking_pending';

    public const STATUS_BOOKED = 'booked';

    public const STATUS_PICKED_UP = 'picked_up';

    public const STATUS_IN_TRANSIT = 'in_transit';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_PARTIAL_DELIVERED = 'partial_delivered';

    public const STATUS_RETURNED = 'returned';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_FAILED = 'failed';

    public const STATUSES = [
        self::STATUS_BOOKING_PENDING => 'Booking Pending',
        self::STATUS_BOOKED => 'Booked',
        self::STATUS_PICKED_UP => 'Picked Up',
        self::STATUS_IN_TRANSIT => 'In Transit',
        self::STATUS_DELIVERED => 'Delivered',
        self::STATUS_PARTIAL_DELIVERED => 'Partial Delivered',
        self::STATUS_RETURNED => 'Returned',
        self::STATUS_CANCELLED => 'Cancelled',
        self::STATUS_FAILED => 'Failed',
    ];

    protected $fillable = [
        'company_id',
        'courier_provider_id',
        'order_id',
        'tracking_id',
        'provider_reference',
        'recipient_name',
        'recipient_phone',
        'recipient_address',
        'cod_amount',
        'status',
        'booked_at',
        'delivered_at',
        'returned_at',
        'note',
    ];

    protected $casts = [
        'cod_amount' => 'decimal:2',
        'booked_at' => 'datetime',
        'delivered_at' => 'datetime',
        'returned_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (CourierBooking $booking): void {
            $booking->company_id ??= $booking->order?->company_id;
            $booking->status ??= self::STATUS_BOOKING_PENDING;
        });
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(CourierProvider::class, 'courier_provider_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(CourierStatusLog::class);
    }
}
