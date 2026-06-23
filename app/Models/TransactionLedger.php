<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class TransactionLedger extends Model
{
    use BelongsToCompany;

    public const DIRECTIONS = [
        'in' => 'In',
        'out' => 'Out',
    ];

    public const TYPES = [
        'customer_payment' => 'Customer Payment',
        'supplier_payment' => 'Supplier Payment',
        'expense' => 'Expense',
        'income' => 'Income',
    ];

    protected $fillable = [
        'company_id',
        'account_id',
        'type',
        'direction',
        'amount',
        'reference_type',
        'reference_id',
        'transaction_date',
        'note',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (TransactionLedger $ledger): void {
            $ledger->company_id ??= $ledger->account?->company_id;
        });

        static::saving(function (TransactionLedger $ledger): void {
            if ((float) $ledger->amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Transaction amount must be greater than zero.',
                ]);
            }
        });

        static::saved(function (TransactionLedger $ledger): void {
            if ($ledger->wasChanged('account_id')) {
                Account::find($ledger->getOriginal('account_id'))?->syncCurrentBalance();
            }

            $ledger->account?->syncCurrentBalance();
        });

        static::deleted(function (TransactionLedger $ledger): void {
            $ledger->account?->syncCurrentBalance();
        });
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
