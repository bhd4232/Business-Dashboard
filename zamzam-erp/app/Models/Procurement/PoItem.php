<?php

namespace App\Models\Procurement;

use App\Models\Core\Product;
use App\Models\Core\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PoItem extends Model
{
    protected $table = 'po_items';

    protected $fillable = [
        'purchase_order_id', 'product_id', 'product_variant_id',
        'supplier_price_cny', 'quantity', 'approx_weight_kg', 'subtotal_cny',
        'received_qty', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'supplier_price_cny' => 'decimal:2',
            'subtotal_cny'       => 'decimal:2',
            'approx_weight_kg'   => 'decimal:3',
            'quantity'           => 'integer',
            'received_qty'       => 'integer',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function getRemainingQtyAttribute(): int
    {
        return $this->quantity - $this->received_qty;
    }

    protected static function booted(): void
    {
        static::saving(function (PoItem $item) {
            $item->subtotal_cny = $item->supplier_price_cny * $item->quantity;
        });
    }
}
