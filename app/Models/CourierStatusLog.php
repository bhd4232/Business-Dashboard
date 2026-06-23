<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourierStatusLog extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'courier_booking_id',
        'from_status',
        'to_status',
        'note',
        'created_by',
    ];

    protected static function booted(): void
    {
        static::creating(function (CourierStatusLog $log): void {
            $log->company_id ??= $log->booking?->company_id;
        });
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(CourierBooking::class, 'courier_booking_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
