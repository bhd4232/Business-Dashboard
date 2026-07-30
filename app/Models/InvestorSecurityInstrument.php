<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class InvestorSecurityInstrument extends Model
{
    use BelongsToCompany;

    public const CHEQUE_STATUSES = ['held_by_investor' => 'Held by investor', 'returned' => 'Returned', 'cashed' => 'Cashed'];

    protected $fillable = ['company_id', 'investment_id', 'cheque_number', 'cheque_bank_name', 'cheque_amount', 'cheque_status', 'guarantor_name', 'guarantor_nid', 'guarantor_phone', 'contract_document_path'];

    protected $casts = ['cheque_amount' => 'decimal:2'];

    protected static function booted(): void
    {
        static::saving(function (self $instrument): void {
            $investment = Investment::query()->find($instrument->investment_id);

            if (! $investment) {
                throw new LogicException('A security instrument requires an investment.');
            }

            $instrument->company_id = $investment->company_id;

            if (blank($instrument->contract_document_path)) {
                return;
            }

            $company = Company::query()->find($instrument->company_id);

            if (! $company || ! str_starts_with((string) $instrument->contract_document_path, $company->storageRoot().'/private/investor-contracts/')) {
                throw new LogicException('Investor contracts must use the owning company private storage path.');
            }
        });
    }

    public function investment()
    {
        return $this->belongsTo(Investment::class);
    }
}
