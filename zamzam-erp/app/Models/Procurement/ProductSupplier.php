<?php

namespace App\Models\Procurement;

use App\Models\Core\Product;
use App\Models\Core\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSupplier extends Model
{
    protected $table = 'product_suppliers';

    protected $fillable = [
        'product_id', 'product_variant_id', 'supplier_id',
        'price_cny', 'moq', 'lead_time_days',
        'supplier_sku', 'product_url', 'is_preferred',
        'last_purchased_at', 'last_purchase_price_cny', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price_cny'               => 'decimal:2',
            'last_purchase_price_cny' => 'decimal:2',
            'is_preferred'            => 'boolean',
            'is_active'               => 'boolean',
            'last_purchased_at'       => 'datetime',
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

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
