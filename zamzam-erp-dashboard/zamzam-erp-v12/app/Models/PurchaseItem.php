<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseItem extends Model
{
    protected $fillable = [
        'purchase_id',
        'product_id',
        'quantity',
        'unit_cost',
        'subtotal',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (PurchaseItem $item): void {
            $item->subtotal = (int) $item->quantity * (float) $item->unit_cost;
        });

        static::saved(function (PurchaseItem $item): void {
            $item->purchase?->syncTotalsAndStock();
        });

        static::deleted(function (PurchaseItem $item): void {
            $item->purchase?->syncTotalsAndStock();
        });
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
