<?php

namespace App\Models\Concerns;

use App\Models\Company;
use App\Scopes\CompanyScope;
use App\Services\CompanyContext;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

trait BelongsToCompany
{
    protected static function bootBelongsToCompany(): void
    {
        static::creating(function ($model): void {
            if (! Schema::hasColumn($model->getTable(), 'company_id') || $model->company_id) {
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
