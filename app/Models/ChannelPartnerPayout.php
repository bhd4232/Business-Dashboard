<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ChannelPartnerPayout extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'settlement_id', 'investor_id', 'amount', 'payment_status', 'paid_at', 'payment_method', 'recipient_name', 'recipient_bank_name', 'recipient_branch', 'recipient_routing_number', 'recipient_account_number', 'payment_reference'];

    protected $casts = ['amount' => 'decimal:2', 'paid_at' => 'date'];

    public function settlement()
    {
        return $this->belongsTo(ProjectSettlement::class);
    }

    public function investor()
    {
        return $this->belongsTo(Investor::class);
    }

    public function payoutItems(): MorphMany
    {
        return $this->morphMany(PayoutItem::class, 'payable');
    }

    /** True while this payout is already moving through a bank/MFS batch — the manual "Mark as Paid" action must stay hidden so it can't double-pay. */
    public function hasActivePayoutItem(): bool
    {
        return $this->payoutItems()->whereIn('status', PayoutItem::ACTIVE_STATUSES)->exists();
    }

    public function hasCompleteBankDetails(): bool
    {
        return filled($this->recipient_bank_name) && filled($this->recipient_routing_number) && filled($this->recipient_account_number);
    }
}
