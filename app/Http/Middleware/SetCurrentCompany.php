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
    /**
     * Once the companies table exists it exists for the rest of the app's
     * life, so we only need to keep re-querying the schema while this is
     * still false (i.e. briefly, before the initial install migrates).
     * Avoids a Schema::hasTable() query on every single request.
     */
    protected static bool $companiesTableExists = false;

    public function handle(Request $request, Closure $next): Response
    {
        $context = app(CompanyContext::class)->clear();

        if (! self::$companiesTableExists) {
            self::$companiesTableExists = Schema::hasTable('companies');
        }

        if (! self::$companiesTableExists) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        $selectedCompany = $request->session()->get('current_company_id');
        $selectionIsExplicit = (bool) $request->session()->get('current_company_selection_explicit', false);

        if ($user->isSuperAdmin() && $selectedCompany === 'all' && $selectionIsExplicit) {
            $request->session()->put('current_company_id', 'all');
            $context->all();

            return $next($request);
        }

        $company = $this->resolveCompany($user, $selectedCompany);

        if ($company) {
            $request->session()->put('current_company_id', $company->getKey());

            if (! is_numeric($selectedCompany) || (int) $selectedCompany !== (int) $company->getKey()) {
                $request->session()->put('current_company_selection_explicit', false);
            }

            $context->set($company);
        } elseif ($user->isSuperAdmin()) {
            $request->session()->put('current_company_id', 'all');
            $request->session()->put('current_company_selection_explicit', false);
            $context->all();
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

        $defaultCompany = $user->defaultCompany();

        if ($defaultCompany) {
            return $defaultCompany;
        }

        return $user->isSuperAdmin() ? null : $query->first();
    }
}
