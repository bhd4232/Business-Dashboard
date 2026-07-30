<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class ChannelPartnerPayout extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'settlement_id', 'investor_id', 'amount', 'payment_status', 'paid_at', 'payment_method', 'payment_reference'];

    protected $casts = ['amount' => 'decimal:2', 'paid_at' => 'date'];

    public function settlement()
    {
        return $this->belongsTo(ProjectSettlement::class);
    }

    public function investor()
    {
        return $this->belongsTo(Investor::class);
    }
}
