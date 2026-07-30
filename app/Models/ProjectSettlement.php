<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class ProjectSettlement extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'project_id', 'total_revenue', 'total_cost', 'net_profit', 'investor_pool_amount', 'channel_partner_amount', 'company_net_amount', 'annualized_return_percent', 'status', 'settled_by', 'settled_at'];

    protected $casts = ['total_revenue' => 'decimal:2', 'total_cost' => 'decimal:2', 'net_profit' => 'decimal:2', 'investor_pool_amount' => 'decimal:2', 'channel_partner_amount' => 'decimal:2', 'company_net_amount' => 'decimal:2', 'annualized_return_percent' => 'decimal:2', 'settled_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(function (self $settlement): void {
            $immutable = ['project_id', 'total_revenue', 'total_cost', 'net_profit', 'investor_pool_amount', 'channel_partner_amount', 'company_net_amount', 'annualized_return_percent', 'settled_by', 'settled_at'];

            if ($settlement->isDirty($immutable)) {
                throw ValidationException::withMessages(['settlement' => 'Confirmed settlement amounts are immutable. Create a reversal before correcting them.']);
            }
        });
    }

    public function project()
    {
        return $this->belongsTo(InvestmentProject::class, 'project_id');
    }

    public function settledBy()
    {
        return $this->belongsTo(User::class, 'settled_by');
    }

    public function payouts()
    {
        return $this->hasMany(SettlementPayout::class, 'settlement_id');
    }

    public function channelPartnerPayouts()
    {
        return $this->hasMany(ChannelPartnerPayout::class, 'settlement_id');
    }
}
