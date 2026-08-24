<?php

namespace App\Models\Concerns;

use App\Models\Company;
use App\Scopes\CompanyScope;
use App\Services\CompanyContext;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

trait BelongsToCompany
{
    /**
     * Traits get a separate copy of a static property per consuming class,
     * so this caches per model. Once the company_id column exists it exists
     * for the rest of the app's life, so we only need to keep re-querying
     * the schema while this is still false (i.e. briefly, before the
     * initial install migrates) — avoids a Schema::hasColumn() query on
     * every single model creation.
     */
    protected static bool $companyIdColumnExists = false;

    protected static function bootBelongsToCompany(): void
    {
        static::creating(function ($model): void {
            if (! self::$companyIdColumnExists) {
                self::$companyIdColumnExists = Schema::hasColumn($model->getTable(), 'company_id');
            }

            if (! self::$companyIdColumnExists || $model->company_id) {
                return;
            }

            if (app()->bound(CompanyContext::class) && app(CompanyContext::class)->hasCompany()) {
                $model->company_id = app(CompanyContext::class)->id();

                return;
            }

            $model->company_id = Company::defaultCompanyId();
        });

        static::addGlobalScope(new CompanyScope);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
