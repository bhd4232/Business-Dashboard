<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LogicException;

class Company extends Model
{
    public const CORE_COMPANIES = [
        [
            'name' => 'Garments Machinery Company',
            'slug' => 'garments-machinery',
            'business_type' => 'Garments Machinery',
            'invoice_prefix' => 'GM',
            'domain' => 'tasneemknitindustry.com',
        ],
        [
            'name' => 'Solar Items Company',
            'slug' => 'solar-items',
            'business_type' => 'Solar Items',
            'invoice_prefix' => 'SOL',
            'domain' => 'noorsolaren.com',
        ],
        [
            'name' => 'Gadget Items Company',
            'slug' => 'gadget-items',
            'business_type' => 'Gadget Items',
            'invoice_prefix' => 'GAD',
            'domain' => 'zamzamgadgetbd.com',
        ],
        [
            'name' => 'Gift Items Company',
            'slug' => 'gift-items',
            'business_type' => 'Gift Items',
            'invoice_prefix' => 'GFT',
            'domain' => 'zamzamint.com',
        ],
    ];

    protected $fillable = [
        'name',
        'slug',
        'domain',
        'domain_verified',
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
        'dashboard_color',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'domain_verified' => 'boolean',
        'settings' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (Company $company): void {
            if (! $company->exists && blank($company->storage_key)) {
                $company->storage_key = (string) Str::uuid();
            }

            if (! $company->exists && ! Str::isUuid((string) $company->storage_key)) {
                throw new LogicException('A company storage key must be a UUID.');
            }

            if ($company->exists && $company->isDirty('storage_key')) {
                throw new LogicException('A company storage key is immutable once assigned.');
            }

            $company->slug = $company->slug ?: Str::slug($company->name);
            $company->currency = $company->currency ?: 'BDT';
            $company->timezone = $company->timezone ?: config('app.timezone', 'Asia/Dhaka');
            $company->invoice_prefix = static::normalizeInvoicePrefix($company->invoice_prefix, $company->name);
            $company->domain = static::normalizeDomain($company->domain);
        });
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role', 'is_default'])
            ->withTimestamps();
    }

    public function storefrontSetting(): HasOne
    {
        return $this->hasOne(StorefrontSetting::class);
    }

    public function storageRoot(): string
    {
        if (blank($this->storage_key)) {
            throw new LogicException('The company does not have a storage key.');
        }

        return 'companies/'.$this->storage_key;
    }

    public static function normalizeDomain(?string $domain): ?string
    {
        $domain = strtolower(trim((string) $domain));

        if ($domain === '') {
            return null;
        }

        $domain = preg_replace('#^https?://#', '', $domain) ?: $domain;
        $domain = strtok($domain, '/') ?: $domain;
        $domain = preg_replace('/:\d+$/', '', $domain) ?: $domain;

        return str_starts_with($domain, 'www.') ? substr($domain, 4) : $domain;
    }

    public static function normalizeInvoicePrefix(?string $prefix, ?string $companyName = null): string
    {
        $prefix = Str::upper(trim((string) $prefix));

        if ($prefix === '') {
            $prefix = Str::upper(Str::substr(Str::slug((string) $companyName, ''), 0, 3)) ?: 'INV';
        }

        if (strlen($prefix) > 20 || ! preg_match('/^[A-Z0-9-]+$/', $prefix)) {
            throw new LogicException('Invoice prefixes may contain only uppercase letters, numbers, and hyphens, up to 20 characters.');
        }

        return $prefix;
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
            $record = static::query()->firstOrCreate(
                ['slug' => $company['slug']],
                [
                    'name' => $company['name'],
                    'business_type' => $company['business_type'],
                    'currency' => 'BDT',
                    'timezone' => config('app.timezone', 'Asia/Dhaka'),
                    'invoice_prefix' => $company['invoice_prefix'],
                    'domain' => $company['domain'],
                    'domain_verified' => false,
                    'is_active' => true,
                    'settings' => [],
                ],
            );

            if (blank($record->domain)) {
                $record->forceFill(['domain' => $company['domain']])->save();
            }
        }
    }
}
