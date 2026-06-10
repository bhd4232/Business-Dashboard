<?php

namespace App\Models\Inventory;

use App\Models\Core\Product;
use App\Models\Core\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdjustmentItem extends Model
{
    protected $table = 'adjustment_items';

    protected $fillable = [
        'stock_adjustment_id', 'product_id', 'product_variant_id',
        'quantity_before', 'quantity_adjusted', 'quantity_after',
    ];

    protected function casts(): array
    {
        return [
            'quantity_before'   => 'integer',
            'quantity_adjusted' => 'integer',
            'quantity_after'    => 'integer',
        ];
    }

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class, 'stock_adjustment_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function getDifferenceAttribute(): int
    {
        return $this->quantity_after - $this->quantity_before;
    }
}
