<?php

namespace App\Models;

use App\Models\Concerns\ValidatesEmailAddress;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use ValidatesEmailAddress;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'address',
        'company_name',
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
        static::saving(function (Supplier $supplier): void {
            static::validateEmailAttribute($supplier);
        });

        static::saved(function (Supplier $supplier): void {
            $supplier->syncCurrentBalance();
        });
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public function syncCurrentBalance(): void
    {
        $balance = (float) $this->opening_balance + (float) $this->purchases()
            ->where('status', 'received')
            ->sum('due_amount') - (float) $this->payments()->sum('amount');

        if ($this->current_balance != $balance) {
            $this->forceFill(['current_balance' => $balance])->saveQuietly();
        }
    }
}
