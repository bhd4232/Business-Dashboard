<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseItem extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'purchase_id',
        'product_id',
        'hs_code',
        'spec_note',
        'quantity',
        'unit_cost',
        'fob_unit_price_usd',
        'subtotal',
        'allocated_cost',
        'landed_unit_cost',
        'net_weight_kg',
        'gross_weight_kg',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'fob_unit_price_usd' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'allocated_cost' => 'decimal:2',
        'landed_unit_cost' => 'decimal:2',
        'net_weight_kg' => 'decimal:3',
        'gross_weight_kg' => 'decimal:3',
    ];

    protected static function booted(): void
    {
        static::creating(function (PurchaseItem $item): void {
            $item->company_id ??= $item->purchase?->company_id;
        });

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
