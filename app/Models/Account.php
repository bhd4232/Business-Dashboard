<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Services\AccountBalanceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class Account extends Model
{
    use BelongsToCompany;

    public const MANUAL_TYPES = [
        'cash' => 'Cash',
        'bank' => 'Bank',
        'mobile_banking' => 'Mobile Banking',
    ];

    public const SYSTEM_TYPES = [
        'inventory' => 'Inventory',
        'customer_due' => 'Customer Due',
        'shipment' => 'Shipment',
    ];

    public const TYPES = [
        ...self::MANUAL_TYPES,
        ...self::SYSTEM_TYPES,
    ];

    public const SYSTEM_ACCOUNTS = [
        'inventory_value' => [
            'name' => 'Inventory Value',
            'type' => 'inventory',
        ],
        'customer_due' => [
            'name' => 'Customer Due',
            'type' => 'customer_due',
        ],
        'new_shipment' => [
            'name' => 'New Shipment',
            'type' => 'shipment',
        ],
    ];

    protected $fillable = [
        'company_id',
        'name',
        'type',
        'system_key',
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

        static::updating(function (Account $account): void {
            if ($account->isSystem() && $account->isDirty(['company_id', 'name', 'type', 'system_key', 'is_active'])) {
                throw ValidationException::withMessages([
                    'account' => 'Permanent system accounts cannot be edited.',
                ]);
            }
        });

        static::deleting(function (Account $account): void {
            if ($account->isSystem()) {
                throw ValidationException::withMessages([
                    'account' => 'Permanent system accounts cannot be deleted.',
                ]);
            }
        });
    }

    public function scopeManual(Builder $query): Builder
    {
        return $query->whereNull('system_key');
    }

    public function isSystem(): bool
    {
        return filled($this->system_key);
    }

    public function balance(): float
    {
        if (! $this->isSystem()) {
            return (float) $this->current_balance;
        }

        return app(AccountBalanceService::class)->for($this);
    }

    public static function ensureSystemAccountsForCompany(Company|int $company): void
    {
        if (! Schema::hasTable('accounts') || ! Schema::hasColumn('accounts', 'system_key')) {
            return;
        }

        $companyId = $company instanceof Company ? $company->getKey() : $company;

        foreach (self::SYSTEM_ACCOUNTS as $key => $definition) {
            static::withoutGlobalScopes()->firstOrCreate(
                [
                    'company_id' => $companyId,
                    'system_key' => $key,
                ],
                [
                    ...$definition,
                    'opening_balance' => 0,
                    'current_balance' => 0,
                    'is_active' => true,
                ],
            );
        }
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
        if ($this->isSystem()) {
            return;
        }

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
