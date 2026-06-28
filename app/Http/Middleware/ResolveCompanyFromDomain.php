<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Services\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveCompanyFromDomain
{
    public function __construct(protected CompanyContext $context) {}

    public function handle(Request $request, Closure $next, string $mode = 'required'): Response
    {
        $host = Company::normalizeDomain($request->getHost());

        $company = $host
            ? Company::query()
                ->where('domain', $host)
                ->where('is_active', true)
                ->first()
            : null;

        if ($company) {
            $this->context->set($company);
            $request->attributes->set('storefront_company', $company);

            return $next($request);
        }

        $this->context->clear();

        if ($mode === 'optional') {
            return $next($request);
        }

        abort(404);
    }
}
