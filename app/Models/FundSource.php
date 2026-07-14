<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FundSource extends Model
{
    use BelongsToCompany;

    public const TYPES = [
        'cash' => 'Cash',
        'bank' => 'Bank',
        'mobile_banking' => 'Mobile Banking',
        'wallet' => 'Wallet',
        'petty_cash' => 'Petty Cash',
        'owner_investment' => 'Owner Investment',
        'partner_investment' => 'Partner Investment',
        'business_profit' => 'Business Profit',
        'bank_loan' => 'Bank Loan',
        'customer_advance' => 'Customer Advance',
        'supplier_credit' => 'Supplier Credit',
        'other' => 'Other',
    ];

    /** Types that wrap an existing Account rather than tracking their own balance. */
    public const ACCOUNT_LINKED_TYPES = ['cash', 'bank', 'mobile_banking', 'wallet', 'petty_cash'];

    protected $fillable = [
        'company_id',
        'name',
        'type',
        'account_id',
        'opening_balance',
        'is_active',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }

    public function isAccountLinked(): bool
    {
        return in_array($this->type, self::ACCOUNT_LINKED_TYPES, true);
    }

    /**
     * Current balance. Account-linked types (cash/bank/mobile banking/etc.)
     * always read the linked Account's ledger-derived balance — never a
     * separately stored figure, to avoid the two numbers ever diverging.
     * Capital-type sources (investment/profit/loan/credit) track their own
     * running balance via approved vouchers routed through this fund source.
     */
    public function balance(): float
    {
        if ($this->isAccountLinked()) {
            return (float) ($this->account?->current_balance ?? 0);
        }

        $in = (float) $this->vouchers()
            ->where('type', 'credit')
            ->where('status', 'approved')
            ->sum('amount');

        $out = (float) $this->vouchers()
            ->where('type', 'debit')
            ->where('status', 'approved')
            ->sum('amount');

        return (float) $this->opening_balance + $in - $out;
    }
}
