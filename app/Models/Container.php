<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Container extends Model
{
    use BelongsToCompany;

    public const STATUSES = ['planned' => 'Planned', 'booked' => 'Booked', 'in_transit' => 'In Transit', 'customs' => 'Customs', 'arrived' => 'Arrived', 'released' => 'Released', 'cancelled' => 'Cancelled'];

    protected $fillable = ['company_id', 'container_number', 'shipping_line', 'origin', 'destination', 'status', 'estimated_departure', 'actual_departure', 'estimated_arrival', 'actual_arrival', 'notes'];

    protected $casts = ['estimated_departure' => 'date', 'actual_departure' => 'date', 'estimated_arrival' => 'date', 'actual_arrival' => 'date'];

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }
}
