<?php

namespace App\Models\Inventory;

use App\Models\Core\Product;
use App\Models\Core\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockItem extends Model
{
    protected $fillable = [
        'product_id', 'product_variant_id', 'warehouse_id',
        'quantity', 'reserved_qty', 'avg_landing_cost_bdt',
    ];

    protected function casts(): array
    {
        return [
            'quantity'              => 'integer',
            'reserved_qty'          => 'integer',
            'avg_landing_cost_bdt'  => 'decimal:4',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function getAvailableQtyAttribute(): int
    {
        return $this->quantity - $this->reserved_qty;
    }

    public function getTotalValueBdtAttribute(): float
    {
        return $this->quantity * $this->avg_landing_cost_bdt;
    }

    public function isLowStock(): bool
    {
        return $this->quantity <= $this->product->min_stock_alert;
    }
}
