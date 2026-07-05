<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\ValidatesEmailAddress;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Customer extends Model
{
    use BelongsToCompany, ValidatesEmailAddress;

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
        'company_id',
        'name',
        'phone',
        'email',
        'address',
        'customer_type',
        'customer_source',
        'reseller_status',
        'business_name',
        'reseller_note',
        'opening_balance',
        'current_balance',
        'is_active',
    ];

    public const RESELLER_STATUSES = [
        'none' => 'Not a reseller',
        'pending' => 'Application pending',
        'approved' => 'Approved reseller',
        'rejected' => 'Rejected',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (Customer $customer): void {
            static::validateEmailAttribute($customer);
        });

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

    public function riskProfile(): HasOne
    {
        return $this->hasOne(CustomerRiskProfile::class)->latestOfMany();
    }

    public static function typeOptions(): array
    {
        $customTypes = static::query()
            ->whereNotNull('customer_type')
            ->where('customer_type', '!=', '')
            ->distinct()
            ->pluck('customer_type')
            ->mapWithKeys(fn (string $type): array => [$type => static::typeLabel($type)])
            ->all();

        return self::TYPES + $customTypes;
    }

    public static function typeKey(string $type): string
    {
        $type = trim($type);

        return Str::limit($type, 50, '') ?: 'regular';
    }

    public static function typeLabel(?string $type): ?string
    {
        if (blank($type)) {
            return null;
        }

        if (array_key_exists($type, self::TYPES)) {
            return self::TYPES[$type];
        }

        if (str_contains($type, ' ') && preg_match('/[A-Z]/', $type)) {
            return $type;
        }

        return Str::of($type)->replace(['_', '-'], ' ')->title()->toString();
    }

    public static function sourceOptions(): array
    {
        $customSources = static::query()
            ->whereNotNull('customer_source')
            ->where('customer_source', '!=', '')
            ->distinct()
            ->pluck('customer_source')
            ->mapWithKeys(fn (string $source): array => [$source => static::sourceLabel($source)])
            ->all();

        return self::SOURCES + $customSources;
    }

    public static function sourceKey(string $source): string
    {
        $source = trim($source);

        return Str::limit($source, 50, '') ?: 'other';
    }

    public static function sourceLabel(?string $source): ?string
    {
        if (blank($source)) {
            return null;
        }

        if (array_key_exists($source, self::SOURCES)) {
            return self::SOURCES[$source];
        }

        if (str_contains($source, ' ') && preg_match('/[A-Z]/', $source)) {
            return $source;
        }

        return Str::of($source)->replace(['_', '-'], ' ')->title()->toString();
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
