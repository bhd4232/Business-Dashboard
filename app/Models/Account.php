<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use BelongsToCompany;

    public const TYPES = [
        'cash' => 'Cash',
        'bank' => 'Bank',
        'mobile_banking' => 'Mobile Banking',
    ];

    protected $fillable = [
        'company_id',
        'name',
        'type',
        'opening_balance',
        'current_balance',
        'is_active',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saved(function (Account $account): void {
            $account->syncCurrentBalance();
        });
    }

    public function ledgers(): HasMany
    {
        return $this->hasMany(TransactionLedger::class);
    }

    public function customerPayments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class);
    }

    public function supplierPayments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function syncCurrentBalance(): void
    {
        $in = (float) $this->ledgers()->where('direction', 'in')->sum('amount');
        $out = (float) $this->ledgers()->where('direction', 'out')->sum('amount');
        $balance = (float) $this->opening_balance + $in - $out;

        if ($this->current_balance != $balance) {
            $this->forceFill(['current_balance' => $balance])->saveQuietly();
        }
    }

    public function projectedBalanceAfterOutflow(float $amount, ?int $excludingLedgerId = null): float
    {
        $in = (float) $this->ledgers()
            ->when($excludingLedgerId, fn ($query) => $query->whereKeyNot($excludingLedgerId))
            ->where('direction', 'in')
            ->sum('amount');

        $out = (float) $this->ledgers()
            ->when($excludingLedgerId, fn ($query) => $query->whereKeyNot($excludingLedgerId))
            ->where('direction', 'out')
            ->sum('amount');

        return (float) $this->opening_balance + $in - $out - $amount;
    }
}
