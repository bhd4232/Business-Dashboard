<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorefrontCheckoutAttempt extends Model
{
    use BelongsToCompany;

    public const OUTCOME_ALLOWED = 'allowed';

    public const OUTCOME_OBSERVED = 'observed';

    public const OUTCOME_BLOCKED = 'blocked';

    public const OUTCOME_PENDING_PAYMENT = 'pending_payment';

    public const OUTCOME_ACCEPTED = 'accepted';

    protected $fillable = [
        'company_id',
        'order_id',
        'phone_hash',
        'email_hash',
        'ip_hash',
        'phone_mask',
        'email_mask',
        'ip_mask',
        'policy_mode',
        'outcome',
        'violations',
        'courier_success_ratio',
        'required_advance',
        'advance_reasons',
        'cart_total',
        'payment_method',
    ];

    protected $casts = [
        'violations' => 'array',
        'courier_success_ratio' => 'decimal:2',
        'required_advance' => 'decimal:2',
        'advance_reasons' => 'array',
        'cart_total' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
