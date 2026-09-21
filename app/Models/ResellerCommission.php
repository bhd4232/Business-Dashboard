<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ResellerCommission extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'order_id', 'reseller_customer_id', 'gross_margin_amount',
        'other_costs_amount', 'commission_amount', 'cost_breakdown', 'status',
        'order_delivered_at', 'holding_until', 'promoted_to_payable_at',
        'paid_at', 'reversed_at', 'reversal_reason',
    ];

    protected $casts = [
        'gross_margin_amount' => 'decimal:2',
        'other_costs_amount' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'cost_breakdown' => 'array',
        'order_delivered_at' => 'date',
        'holding_until' => 'date',
        'promoted_to_payable_at' => 'datetime',
        'paid_at' => 'datetime',
        'reversed_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function resellerCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'reseller_customer_id');
    }

    public function payoutItems(): MorphMany
    {
        return $this->morphMany(PayoutItem::class, 'payable');
    }

    public function hasActivePayoutItem(): bool
    {
        return $this->payoutItems()->whereIn('status', PayoutItem::ACTIVE_STATUSES)->exists();
    }

    /** Whether the reseller has recorded enough payout details for their chosen method to be included in a batch. */
    public function hasCompletePayoutDetails(): bool
    {
        $reseller = $this->resellerCustomer;

        if (! $reseller || blank($reseller->reseller_payout_method)) {
            return false;
        }

        $details = $reseller->reseller_payout_details ?? [];

        return $reseller->reseller_payout_method === 'bank'
            ? filled($details['bank_name'] ?? null) && filled($details['routing_number'] ?? null) && filled($details['account_number'] ?? null)
            : filled($details['msisdn'] ?? null);
    }
}
