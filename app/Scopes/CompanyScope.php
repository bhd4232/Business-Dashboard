<?php

namespace App\Scopes;

use App\Services\CompanyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! app()->bound(CompanyContext::class)) {
            return;
        }

        $context = app(CompanyContext::class);

        if ($context->deniesCompanyAccess()) {
            $builder->whereRaw('1 = 0');

            return;
        }

        if ($context->isAllCompanies() || ! $context->hasCompany()) {
            return;
        }

        $builder->where($model->qualifyColumn('company_id'), $context->id());
    }
}
