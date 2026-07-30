<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class SettlementPayout extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'settlement_id', 'investor_id', 'investment_amount', 'profit_share_amount', 'total_payout', 'payment_status', 'paid_at', 'payment_method', 'recipient_name', 'recipient_bank_name', 'recipient_branch', 'recipient_account_number', 'payment_reference'];

    protected $casts = ['investment_amount' => 'decimal:2', 'profit_share_amount' => 'decimal:2', 'total_payout' => 'decimal:2', 'paid_at' => 'date'];

    public function settlement()
    {
        return $this->belongsTo(ProjectSettlement::class);
    }

    public function investor()
    {
        return $this->belongsTo(Investor::class);
    }
}
