<?php

namespace App\Models\Sales;

use App\Models\Core\Product;
use App\Models\Core\ProductVariant;
use Illuminate\Database\Eloquent\Model;

class SoItem extends Model
{
    protected $table = 'so_items';

    protected $fillable = [
        'sales_order_id', 'product_id', 'product_variant_id',
        'quantity', 'unit_price_bdt', 'discount_percent',
        'subtotal_bdt', 'unit_landing_cost_bdt',
    ];

    protected function casts(): array
    {
        return [
            'unit_price_bdt'        => 'decimal:2',
            'discount_percent'      => 'decimal:2',
            'subtotal_bdt'          => 'decimal:2',
            'unit_landing_cost_bdt' => 'decimal:4',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (SoItem $item) {
            $price    = (float) $item->unit_price_bdt;
            $qty      = (int)   $item->quantity;
            $discount = (float) $item->discount_percent;

            $item->subtotal_bdt = round($price * $qty * (1 - $discount / 100), 2);
        });
    }

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
