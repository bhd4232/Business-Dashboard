<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'unit_price',
        'subtotal',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (OrderItem $item): void {
            $item->subtotal = (int) $item->quantity * (float) $item->unit_price;
        });

        static::saved(function (OrderItem $item): void {
            $item->order?->syncTotalsStockAndCustomerBalance();
        });

        static::deleted(function (OrderItem $item): void {
            $item->order?->syncTotalsStockAndCustomerBalance();
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
