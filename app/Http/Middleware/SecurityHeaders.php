<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security headers for every response (storefront and admin alike).
 * Content-Security-Policy is deliberately NOT set here — see
 * 09_DASHBOARD_WOOCOMMERCE_SECURITY_PLAN.md step 3.4: a wrong CSP can break
 * the whole site (inline scripts, CDN-loaded Alpine/Chart.js/pixel scripts),
 * and needs its own carefully-tested pass.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        return $response;
    }
}
