<?php

namespace App\Scopes;

use App\Services\CompanyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Constrains company-owned models to the active CompanyContext.
 *
 * Context states and their query behaviour:
 * - set(company)  → filtered to that company_id.
 * - none()        → fail-closed: returns nothing (`1 = 0`).
 * - all()         → unscoped: every company (explicit cross-company read).
 * - cleared/unset → unscoped, same as all().
 *
 * The last case is deliberate but sharp: any query that runs before a caller
 * has explicitly set()/none()/all() the context reads across ALL companies.
 * `SetCurrentCompany` clears the context at the start of every request and
 * only leaves it cleared for guests and the `:optional` storefront path; the
 * storefront/domain controllers then set() it (and additionally verify
 * `order->company_id` on route-model-bound records). When adding a new guest
 * route, console command, or view composer that touches a company-owned model,
 * you MUST set the context first, or the read will span every company.
 * `MultiCompanyIsolationTest` guards the set()/none() boundaries.
 */
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
