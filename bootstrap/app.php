<?php

use App\Http\Middleware\PreventDemoModeWrites;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetCurrentCompany;
use App\Support\StorefrontErrorPages;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('backup:database')
            ->dailyAt((string) config('backup.schedule_time', '02:00'))
            ->withoutOverlapping()
            ->onOneServer();
        $schedule->command('storefront:send-abandoned-cart-reminders')
            ->hourly()
            ->withoutOverlapping()
            ->onOneServer();
        $schedule->command('storefront:retry-meta-events')
            ->everyTenMinutes()
            ->withoutOverlapping()
            ->onOneServer();
        $schedule->command('couriers:sync-statuses')
            ->everyThirtyMinutes()
            ->withoutOverlapping()
            ->onOneServer();
        $schedule->command('quotations:mark-expired')
            ->dailyAt('00:30')
            ->withoutOverlapping()
            ->onOneServer();
        $schedule->command('release:notify-deploy')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->onOneServer();
    })
    ->withMiddleware(function (Middleware $middleware) {
        // Coolify terminates HTTPS at its Traefik proxy. Trust the forwarded
        // scheme/host so Filament lazy-loaded component assets never fall back
        // to insecure http:// URLs on an https:// admin page.
        $middleware->trustProxies(
            at: env('TRUSTED_PROXIES', '*'),
            headers: SymfonyRequest::HEADER_X_FORWARDED_TRAEFIK,
        );
        $middleware->validateCsrfTokens(except: ['webhooks/couriers/*', 'webhooks/zinipay/*', 'webhooks/woocommerce/*', 'webhooks/meta', 'webhooks/mobile-crash-reports']);
        $middleware->appendToGroup('web', PreventDemoModeWrites::class);
        $middleware->appendToGroup('web', SetCurrentCompany::class);
        $middleware->appendToGroup('web', SecurityHeaders::class);
        // Company context must be bound before route model binding runs,
        // otherwise CompanyScope cannot constrain implicit bindings and a
        // record from another company could resolve on admin routes.
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: SetCurrentCompany::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Defense-in-depth for a misconfigured production APP_DEBUG=true (see
        // 09_DASHBOARD_WOOCOMMERCE_SECURITY_PLAN.md step 3.2): a storefront
        // visitor must never see a raw stack trace / file paths, even if the
        // server's real .env is wrong. Admin panel, Livewire, and API/webhook
        // requests are left completely alone — Filament and Laravel's own
        // JSON error handling for those must not be touched.
        $exceptions->render(function (\Throwable $e, Request $request) {
            if (app()->environment('local', 'testing')) {
                return null;
            }

            if (! StorefrontErrorPages::appliesTo($request)) {
                return null;
            }

            $status = StorefrontErrorPages::statusFor($e);

            // Deliberately NOT resources/views/errors/{status}.blade.php:
            // Laravel's own exception handler auto-discovers views at that
            // exact conventional path (the "errors::" view namespace) for
            // ANY HTML-rendering exception app-wide, completely bypassing
            // the appliesTo() exclusion above — a webhook/admin/API request
            // that doesn't send `Accept: application/json` would still get
            // this branded page even though appliesTo() said no. Keeping
            // these views under storefront.errors.* instead means they're
            // only ever reachable through this explicit render() call.
            if (! view()->exists("storefront.errors.{$status}")) {
                return null;
            }

            return response()->view("storefront.errors.{$status}", [], $status);
        });
    })->create();
