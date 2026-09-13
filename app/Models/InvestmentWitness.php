<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * A witness named in the investor deed paper's signature block (v3 P1.6).
 * One row per witness, tied to the investment the deed covers.
 */
class InvestmentWitness extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'investment_id', 'name', 'phone', 'address', 'signed_date'];

    protected $casts = ['signed_date' => 'date'];

    protected static function booted(): void
    {
        static::saving(function (self $witness): void {
            $investment = Investment::query()->find($witness->investment_id);

            if (! $investment) {
                throw new LogicException('A witness requires an investment.');
            }

            $witness->company_id = $investment->company_id;
        });
    }

    public function investment()
    {
        return $this->belongsTo(Investment::class);
    }
}
