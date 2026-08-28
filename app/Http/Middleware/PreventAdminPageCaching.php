<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The admin panel has no explicit Cache-Control on its HTML responses, so a
 * browser (or an intermediate proxy) is free to serve a previously cached
 * copy of a page instead of hitting the server again — this is exactly what
 * made the header company switcher look broken: `CompanySwitchController`
 * correctly updates the session and redirects, but the browser's next GET
 * for the same URL could be answered from cache with the pre-switch HTML
 * (including the header's own company label), until a hard refresh forced a
 * real request. Every response every admin page renders is per-user,
 * per-company, and never meant to be reused across requests, so it must
 * never be cached anywhere.
 */
class PreventAdminPageCaching
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }
}
