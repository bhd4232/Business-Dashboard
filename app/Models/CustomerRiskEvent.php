<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerRiskEvent extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'customer_risk_profile_id', 'customer_id', 'order_id', 'event_type', 'score_change', 'metadata'];

    protected $casts = ['score_change' => 'integer', 'metadata' => 'array'];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(CustomerRiskProfile::class, 'customer_risk_profile_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
