<?php

namespace App\Support;

use Illuminate\Http\Request;
use Throwable;

/**
 * Decides whether an unhandled exception should be shown to the visitor as
 * the generic, brand-neutral storefront error page instead of Laravel's own
 * default rendering (raw debug page if APP_DEBUG is misconfigured true, or
 * its normal HTML/JSON error response otherwise). See bootstrap/app.php's
 * withExceptions() and 09_DASHBOARD_WOOCOMMERCE_SECURITY_PLAN.md step 3.2.
 *
 * Kept as a small, pure, directly-unit-testable class rather than inline in
 * bootstrap/app.php's closure — asserting "did the exception handler choose
 * to intervene" through a real thrown-exception HTTP round trip is fragile
 * in tests (Laravel's own default exception rendering behaves differently
 * under PHPUnit than in a real request), whereas this classification logic
 * has nothing to do with how the exception is actually rendered.
 */
class StorefrontErrorPages
{
    /** @var array<int, int> HTTP statuses this covers; anything else maps to 500. */
    public const STATUSES = [403, 404, 419, 429, 500];

    public static function appliesTo(Request $request): bool
    {
        // Admin panel, Livewire's own AJAX updates (any request it makes,
        // regardless of its randomized endpoint prefix), and API/webhook
        // callers must keep Laravel's/Filament's own error handling exactly
        // as-is — this is for full-page storefront visits only.
        if ($request->is('admin', 'admin/*', 'api/*', 'webhooks/*')) {
            return false;
        }

        if ($request->header('X-Livewire')) {
            return false;
        }

        if ($request->expectsJson()) {
            return false;
        }

        return true;
    }

    public static function statusFor(Throwable $e): int
    {
        $status = method_exists($e, 'getStatusCode') ? (int) $e->getStatusCode() : 500;

        return in_array($status, self::STATUSES, true) ? $status : 500;
    }
}
