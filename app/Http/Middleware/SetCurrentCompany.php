<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\User;
use App\Services\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        $context = app(CompanyContext::class)->clear();

        if (! Schema::hasTable('companies')) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        $selectedCompany = $request->session()->get('current_company_id');

        if ($user->isSuperAdmin() && ($selectedCompany === null || $selectedCompany === 'all')) {
            $request->session()->put('current_company_id', 'all');
            $context->all();

            return $next($request);
        }

        $company = $this->resolveCompany($user, $selectedCompany);

        if ($company) {
            $request->session()->put('current_company_id', $company->getKey());
            $context->set($company);
        } else {
            $context->none();
        }

        return $next($request);
    }

    protected function resolveCompany(User $user, mixed $selectedCompany): ?Company
    {
        $query = $user->accessibleCompanies();

        if (is_numeric($selectedCompany)) {
            $company = (clone $query)->whereKey((int) $selectedCompany)->first();

            if ($company) {
                return $company;
            }
        }

        if (! $user->isSuperAdmin()) {
            $defaultCompany = $user->companies()
                ->wherePivot('is_default', true)
                ->where('companies.is_active', true)
                ->first();

            return $defaultCompany ?: $query->first();
        }

        return Company::query()->where('is_active', true)->orderBy('name')->first();
    }
}
