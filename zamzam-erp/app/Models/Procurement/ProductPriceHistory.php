<?php

namespace App\Models\Procurement;

use App\Models\Core\Product;
use App\Models\Core\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPriceHistory extends Model
{
    protected $table = 'product_price_history';

    public $timestamps = false;

    protected $fillable = [
        'product_id', 'product_variant_id', 'supplier_id',
        'purchase_order_id', 'price_cny', 'price_bdt',
        'exchange_rate', 'qty', 'recorded_at', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'price_cny'     => 'decimal:2',
            'price_bdt'     => 'decimal:2',
            'exchange_rate' => 'decimal:6',
            'recorded_at'   => 'date',
            'created_at'    => 'datetime',
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

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }
}
