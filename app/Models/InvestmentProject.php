<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\GeneratesSequentialNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class InvestmentProject extends Model
{
    use BelongsToCompany, GeneratesSequentialNumber;

    public const DURATIONS = ['2_month' => '2 months', '6_month' => '6 months', '12_month' => '12 months', 'custom_days' => 'Custom days'];

    public const STATUSES = ['open' => 'Open', 'running' => 'Running', 'closed' => 'Closed', 'settled' => 'Settled'];

    protected $fillable = ['company_id', 'project_code', 'name', 'description', 'deal_reference', 'purchase_id', 'duration_type', 'trade_cycle_days', 'start_date', 'end_date', 'target_amount', 'investor_share_percent', 'channel_partner_share_percent', 'company_share_percent', 'status'];

    protected $casts = ['start_date' => 'date', 'end_date' => 'date', 'target_amount' => 'decimal:2', 'investor_share_percent' => 'decimal:2', 'channel_partner_share_percent' => 'decimal:2', 'company_share_percent' => 'decimal:2', 'trade_cycle_days' => 'integer'];

    protected static function booted(): void
    {
        static::saving(function (self $project): void {
            $sum = (float) $project->investor_share_percent + (float) $project->channel_partner_share_percent + (float) $project->company_share_percent;

            if (abs($sum - 100) > 0.001) {
                throw ValidationException::withMessages(['investor_share_percent' => 'Investor, channel partner, and company shares must total exactly 100%.']);
            }

            if ($project->exists && $project->isDirty('status') && $project->status === 'settled' && ! $project->settlement()->exists()) {
                throw ValidationException::withMessages(['status' => 'A project can only become settled through Calculate & Settle.']);
            }

            $project->trade_cycle_days = match ($project->duration_type) {
                '2_month' => 60, '6_month' => 180, '12_month' => 365,
                default => $project->trade_cycle_days,
            };
        });

        static::creating(function (self $project): void {
            $project->project_code ??= static::nextProjectCode((int) $project->company_id);
        });
    }

    public static function nextProjectCode(int $companyId): string
    {
        $year = now()->year;
        $sequence = static::withoutGlobalScopes()->where('company_id', $companyId)->whereYear('created_at', $year)->count() + 1;
        $number = str_pad((string) $sequence, 2, '0', STR_PAD_LEFT);

        return "{$number}/{$year}-INV-{$number}";
    }

    protected function sequentialNumberColumn(): string
    {
        return 'project_code';
    }

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    public function investments()
    {
        return $this->hasMany(Investment::class, 'project_id');
    }

    public function costItems()
    {
        return $this->hasMany(ProjectCostItem::class, 'project_id');
    }

    public function settlement()
    {
        return $this->hasOne(ProjectSettlement::class, 'project_id');
    }

    public function totalInvested(): float
    {
        return (float) $this->investments()->sum('amount');
    }

    public function totalCostItems(): float
    {
        return (float) $this->costItems()->sum('amount');
    }

    public function totalLandedCost(): float
    {
        return (float) $this->costItems()->where('category', 'landed_cost')->sum('amount');
    }

    public function totalLocalExpense(): float
    {
        return (float) $this->costItems()->where('category', 'local_expense')->sum('amount');
    }
}
