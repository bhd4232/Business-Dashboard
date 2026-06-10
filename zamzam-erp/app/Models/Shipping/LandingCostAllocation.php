<?php

namespace App\Models\Shipping;

use App\Models\Core\Product;
use App\Models\Core\ProductVariant;
use App\Models\Procurement\PoItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandingCostAllocation extends Model
{
    protected $fillable = [
        'shipment_id', 'po_item_id', 'product_id', 'product_variant_id',
        'quantity', 'weight_kg', 'volume_cm3',
        'purchase_price_cny', 'purchase_price_bdt',
        'allocated_freight_bdt', 'allocated_customs_bdt',
        'allocated_vat_bdt', 'allocated_ait_bdt',
        'allocated_labour_bdt', 'allocated_transport_bdt', 'allocated_other_bdt',
        'landing_cost_per_unit_bdt', 'total_landing_cost_bdt',
    ];

    protected function casts(): array
    {
        return [
            'purchase_price_cny'        => 'decimal:2',
            'purchase_price_bdt'        => 'decimal:2',
            'allocated_freight_bdt'     => 'decimal:4',
            'allocated_customs_bdt'     => 'decimal:4',
            'allocated_vat_bdt'         => 'decimal:4',
            'allocated_ait_bdt'         => 'decimal:4',
            'allocated_labour_bdt'      => 'decimal:4',
            'allocated_transport_bdt'   => 'decimal:4',
            'allocated_other_bdt'       => 'decimal:4',
            'landing_cost_per_unit_bdt' => 'decimal:4',
            'total_landing_cost_bdt'    => 'decimal:4',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function poItem(): BelongsTo
    {
        return $this->belongsTo(PoItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
