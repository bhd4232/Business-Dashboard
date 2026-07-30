<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class Investor extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'name', 'guardian_name', 'phone', 'email', 'address', 'nid_number', 'channel_partner_id', 'channel_partner_change_reason'];

    protected static function booted(): void
    {
        static::saving(function (self $investor): void {
            if ($investor->channel_partner_id && $investor->exists && (int) $investor->channel_partner_id === (int) $investor->getKey()) {
                throw ValidationException::withMessages(['channel_partner_id' => 'An investor cannot be their own channel partner.']);
            }

            if ($investor->channel_partner_id) {
                $partner = self::query()->find($investor->channel_partner_id);

                if (! $partner || (int) $partner->company_id !== (int) $investor->company_id) {
                    throw ValidationException::withMessages(['channel_partner_id' => 'The channel partner must belong to the same company.']);
                }
            }

            if (! $investor->exists || ! $investor->isDirty('channel_partner_id')) {
                return;
            }

            if (! Auth::user()?->hasPermission('investments.manage_channel_partner')) {
                throw ValidationException::withMessages(['channel_partner_id' => 'Only a Super Admin may change an assigned channel partner.']);
            }

            if (blank($investor->channel_partner_change_reason)) {
                throw ValidationException::withMessages(['channel_partner_change_reason' => 'A reason is required when changing an assigned channel partner.']);
            }
        });
    }

    public function channelPartner()
    {
        return $this->belongsTo(self::class, 'channel_partner_id');
    }

    public function referredInvestors()
    {
        return $this->hasMany(self::class, 'channel_partner_id');
    }

    public function investments()
    {
        return $this->hasMany(Investment::class);
    }

    public function settlementPayouts()
    {
        return $this->hasMany(SettlementPayout::class);
    }

    public function totalInvestedLifetime(): float
    {
        return (float) $this->investments()->sum('amount');
    }

    public function totalProfitReceivedLifetime(): float
    {
        return (float) $this->settlementPayouts()->where('payment_status', 'paid')->sum('profit_share_amount');
    }
}
