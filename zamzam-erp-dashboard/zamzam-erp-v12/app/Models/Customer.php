<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    public const TYPES = [
        'regular' => 'Regular',
        'retail' => 'Retail',
        'wholesale' => 'Wholesale',
        'vip' => 'VIP',
    ];

    public const SOURCES = [
        'walk_in' => 'Walk-in',
        'facebook' => 'Facebook',
        'website' => 'Website',
        'referral' => 'Referral',
        'phone_call' => 'Phone Call',
        'other' => 'Other',
    ];

    protected $fillable = [
        'name',
        'phone',
        'email',
        'address',
        'customer_type',
        'customer_source',
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
        static::saved(function (Customer $customer): void {
            $customer->syncCurrentBalance();
        });
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class);
    }

    public function syncCurrentBalance(): void
    {
        $balance = (float) $this->opening_balance + (float) $this->orders()
            ->whereIn('status', ['confirmed', 'completed'])
            ->sum('due_amount') - (float) $this->payments()->sum('amount');

        if ($this->current_balance != $balance) {
            $this->forceFill(['current_balance' => $balance])->saveQuietly();
        }
    }
}
