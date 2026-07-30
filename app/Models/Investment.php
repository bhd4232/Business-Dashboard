<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class Investment extends Model
{
    use BelongsToCompany;

    public const PAYMENT_METHODS = ['cash' => 'Cash', 'bkash' => 'bKash', 'bank' => 'Bank', 'other' => 'Other'];

    protected $fillable = ['company_id', 'project_id', 'investor_id', 'amount', 'payment_method', 'payment_reference', 'invested_at', 'received_by'];

    protected $casts = ['amount' => 'decimal:2', 'invested_at' => 'date'];

    protected static function booted(): void
    {
        static::saving(function (self $investment): void {
            $project = InvestmentProject::query()->find($investment->project_id);
            $investor = Investor::query()->find($investment->investor_id);

            if (! $project || ! $investor || (int) $project->company_id !== (int) $investor->company_id) {
                throw ValidationException::withMessages(['investor_id' => 'Project and investor must belong to the same company.']);
            }

            $investment->company_id = $project->company_id;

            $otherPartnerId = $project->investments()
                ->when($investment->exists, fn ($query) => $query->whereKeyNot($investment->getKey()))
                ->join('investors', 'investors.id', '=', 'investments.investor_id')
                ->whereNotNull('investors.channel_partner_id')
                ->value('investors.channel_partner_id');

            if ($otherPartnerId && $investor->channel_partner_id && (int) $otherPartnerId !== (int) $investor->channel_partner_id) {
                throw ValidationException::withMessages(['investor_id' => 'A project currently supports only one channel partner.']);
            }
        });
    }

    public function project()
    {
        return $this->belongsTo(InvestmentProject::class, 'project_id');
    }

    public function investor()
    {
        return $this->belongsTo(Investor::class);
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function securityInstruments()
    {
        return $this->hasMany(InvestorSecurityInstrument::class);
    }
}
