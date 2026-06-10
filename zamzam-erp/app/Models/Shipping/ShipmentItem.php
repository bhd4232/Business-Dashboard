<?php

namespace App\Models\Shipping;

use App\Models\Core\Product;
use App\Models\Core\ProductVariant;
use App\Models\Procurement\PoItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentItem extends Model
{
    protected $fillable = [
        'shipment_id', 'po_item_id', 'product_id', 'product_variant_id',
        'quantity', 'carton_count', 'weight_kg', 'volume_cm3',
    ];

    protected function casts(): array
    {
        return [
            'weight_kg'   => 'decimal:3',
            'volume_cm3'  => 'decimal:3',
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
