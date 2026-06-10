<?php

namespace App\Models\Sales;

use App\Models\Core\Product;
use App\Models\Core\ProductVariant;
use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id',
        'product_id',
        'product_variant_id',
        'quantity',
        'unit_price_bdt',
        'discount_percent',
        'subtotal_bdt',
    ];

    protected function casts(): array
    {
        return [
            'quantity'          => 'integer',
            'unit_price_bdt'    => 'decimal:2',
            'discount_percent'  => 'decimal:2',
            'subtotal_bdt'      => 'decimal:2',
        ];
    }

    // ─── Auto-calculate subtotal on saving ───────────────────────────────

    protected static function booted(): void
    {
        static::saving(function (self $item) {
            $gross    = (float) $item->quantity * (float) $item->unit_price_bdt;
            $discount = $gross * ((float) $item->discount_percent / 100);
            $item->subtotal_bdt = round($gross - $discount, 2);
        });
    }

    // ─── Relationships ───────────────────────────────────────────────────

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
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
