<?php

use App\Http\Middleware\PreventDemoModeWrites;
use App\Http\Middleware\SetCurrentCompany;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpFoundation\Request;

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
            headers: Request::HEADER_X_FORWARDED_TRAEFIK,
        );
        $middleware->validateCsrfTokens(except: ['webhooks/couriers/*', 'webhooks/zinipay/*', 'webhooks/meta']);
        $middleware->appendToGroup('web', PreventDemoModeWrites::class);
        $middleware->appendToGroup('web', SetCurrentCompany::class);
        // Company context must be bound before route model binding runs,
        // otherwise CompanyScope cannot constrain implicit bindings and a
        // record from another company could resolve on admin routes.
        $middleware->prependToPriorityList(
            before: \Illuminate\Routing\Middleware\SubstituteBindings::class,
            prepend: SetCurrentCompany::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
