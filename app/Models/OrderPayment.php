<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * A single recorded installment against an order (advance, partial, final,
 * etc.) — the order-scoped counterpart to CustomerPayment, which instead
 * funds the customer's running ledger balance and isn't tied to one order.
 * Order::paid_amount / due_amount are kept in sync with the sum of these
 * rows via Order::recalculatePaidAmount(), mirroring how CustomerPayment
 * keeps Customer::current_balance in sync (see CustomerPayment::booted()).
 */
class OrderPayment extends Model
{
    use BelongsToCompany;

    public const TYPE_ADVANCE = 'advance';

    public const TYPE_PARTIAL = 'partial';

    public const TYPE_FINAL = 'final';

    public const TYPES = [
        self::TYPE_ADVANCE => 'Advance',
        self::TYPE_PARTIAL => 'Partial',
        self::TYPE_FINAL => 'Final',
    ];

    // Reuses the same method taxonomy already used for customer-level
    // payments (CustomerPayment::METHODS) rather than inventing a
    // separate, more granular per-gateway enum — keeps reporting/UI
    // consistent across both payment surfaces.
    public const METHODS = CustomerPayment::METHODS;

    protected $fillable = [
        'company_id',
        'order_id',
        'type',
        'method',
        'amount',
        'note',
        'received_by',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (OrderPayment $payment): void {
            $payment->company_id ??= $payment->order?->company_id;
            $payment->type ??= self::TYPE_ADVANCE;
            $payment->method ??= 'cash';
            $payment->paid_at ??= now()->toDateString();
            $payment->received_by ??= Auth::id();
        });

        static::saving(function (OrderPayment $payment): void {
            if ((float) $payment->amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Payment amount must be greater than zero.',
                ]);
            }
        });

        static::saved(function (OrderPayment $payment): void {
            $payment->order?->recalculatePaidAmount();
        });

        static::deleted(function (OrderPayment $payment): void {
            $payment->order?->recalculatePaidAmount();
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
