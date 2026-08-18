<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

/**
 * A manually recorded cost line against an order — courier delivery cost,
 * COD charge, product purchase cost, or a catch-all "other" cost — kept as
 * an itemized, notatable, editable ledger (rather than a single settings-
 * driven number) so staff can log real per-order costs the way they
 * actually happen. Feeds the "Profit" figure on OrderInfolist's Totals
 * section: total_amount - OrderItem COGS - sum(OrderCost.amount).
 */
class OrderCost extends Model
{
    use BelongsToCompany;

    public const HEAD_PURCHASE = 'purchase';

    public const HEAD_COURIER_DELIVERY = 'courier_delivery';

    public const HEAD_COD = 'cod';

    public const HEAD_OTHER = 'other';

    public const HEADS = [
        self::HEAD_PURCHASE => 'Purchase Cost',
        self::HEAD_COURIER_DELIVERY => 'Courier Delivery Cost',
        self::HEAD_COD => 'COD Cost',
        self::HEAD_OTHER => 'Other Cost',
    ];

    protected $fillable = [
        'company_id',
        'order_id',
        'cost_head',
        'amount',
        'note',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (OrderCost $cost): void {
            $cost->company_id ??= $cost->order?->company_id;
            $cost->cost_head ??= self::HEAD_OTHER;
        });

        static::saving(function (OrderCost $cost): void {
            if ((float) $cost->amount < 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Cost amount cannot be negative.',
                ]);
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
