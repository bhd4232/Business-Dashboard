<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PayoutItem extends Model
{
    use BelongsToCompany;

    /** Statuses that mean "still on its way through a batch" — a payable in one of these must not be added to another batch. */
    public const ACTIVE_STATUSES = ['pending', 'in_batch'];

    protected $fillable = [
        'company_id', 'payout_batch_id', 'payable_type', 'payable_id', 'recipient_name',
        'recipient_bank_name', 'recipient_branch', 'recipient_routing_number', 'recipient_account_number',
        'recipient_mfs_number', 'amount', 'status', 'failure_reason', 'bank_transaction_reference', 'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(PayoutBatch::class, 'payout_batch_id');
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }
}
