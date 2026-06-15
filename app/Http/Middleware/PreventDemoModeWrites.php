<?php

namespace App\Http\Middleware;

use App\Services\ProductSetupService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventDemoModeWrites
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe() || ! app(ProductSetupService::class)->demoMode()) {
            return $next($request);
        }

        if (! $request->user() || $request->user()->isSuperAdmin()) {
            return $next($request);
        }

        return response('Demo mode is enabled. Write actions are disabled for demo users.', 423);
    }
}
