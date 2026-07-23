<?php

namespace App\Http\Middleware;

use App\Services\AppUpdateService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SyncAppUpdates
{
    public function __construct(
        protected AppUpdateService $appUpdates,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            try {
                $this->appUpdates->synchronize($request->user());
            } catch (Throwable $exception) {
                // Update discovery must never make the core admin panel
                // unavailable. Report infrastructure errors and let the
                // scheduled command retry the notification delivery.
                report($exception);
            }
        }

        return $next($request);
    }
}
