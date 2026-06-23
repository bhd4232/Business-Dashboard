<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Company extends Model
{
    public const CORE_COMPANIES = [
        [
            'name' => 'Garments Machinery Company',
            'slug' => 'garments-machinery',
            'business_type' => 'Garments Machinery',
            'invoice_prefix' => 'GM',
        ],
        [
            'name' => 'Solar Items Company',
            'slug' => 'solar-items',
            'business_type' => 'Solar Items',
            'invoice_prefix' => 'SOL',
        ],
        [
            'name' => 'Gadget Items Company',
            'slug' => 'gadget-items',
            'business_type' => 'Gadget Items',
            'invoice_prefix' => 'GAD',
        ],
        [
            'name' => 'Gift Items Company',
            'slug' => 'gift-items',
            'business_type' => 'Gift Items',
            'invoice_prefix' => 'GFT',
        ],
    ];

    protected $fillable = [
        'name',
        'slug',
        'business_type',
        'logo',
        'phone',
        'email',
        'address',
        'currency',
        'timezone',
        'invoice_prefix',
        'is_active',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (Company $company): void {
            $company->slug = $company->slug ?: Str::slug($company->name);
            $company->currency = $company->currency ?: 'BDT';
            $company->timezone = $company->timezone ?: config('app.timezone', 'Asia/Dhaka');
            $company->invoice_prefix = Str::upper($company->invoice_prefix ?: Str::substr(Str::slug($company->name, ''), 0, 3) ?: 'INV');
        });
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role', 'is_default'])
            ->withTimestamps();
    }

    public static function defaultCompany(): ?self
    {
        if (! Schema::hasTable('companies')) {
            return null;
        }

        return static::query()->firstOrCreate(
            ['slug' => 'main-company'],
            [
                'name' => 'Main Company',
                'business_type' => 'general',
                'currency' => 'BDT',
                'timezone' => config('app.timezone', 'Asia/Dhaka'),
                'invoice_prefix' => 'MAIN',
                'is_active' => true,
                'settings' => [],
            ],
        );
    }

    public static function defaultCompanyId(): ?int
    {
        return static::defaultCompany()?->getKey();
    }

    public static function seedCoreCompanies(): void
    {
        if (! Schema::hasTable('companies')) {
            return;
        }

        foreach (self::CORE_COMPANIES as $company) {
            static::query()->firstOrCreate(
                ['slug' => $company['slug']],
                [
                    'name' => $company['name'],
                    'business_type' => $company['business_type'],
                    'currency' => 'BDT',
                    'timezone' => config('app.timezone', 'Asia/Dhaka'),
                    'invoice_prefix' => $company['invoice_prefix'],
                    'is_active' => true,
                    'settings' => [],
                ],
            );
        }
    }
}
