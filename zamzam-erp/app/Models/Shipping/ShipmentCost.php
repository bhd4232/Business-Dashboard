<?php

namespace App\Models\Shipping;

use App\Enums\ShipmentCostType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentCost extends Model
{
    protected $fillable = [
        'shipment_id', 'cost_type', 'description',
        'amount_cny', 'amount_usd', 'amount_bdt',
        'exchange_rate', 'paid_at', 'voucher_no',
    ];

    protected function casts(): array
    {
        return [
            'cost_type'     => ShipmentCostType::class,
            'amount_cny'    => 'decimal:2',
            'amount_usd'    => 'decimal:2',
            'amount_bdt'    => 'decimal:2',
            'exchange_rate' => 'decimal:6',
            'paid_at'       => 'datetime',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }
}
