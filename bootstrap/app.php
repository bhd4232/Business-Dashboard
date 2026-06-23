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
    })
    ->withMiddleware(function (Middleware $middleware) {
        // Coolify terminates HTTPS at its Traefik proxy. Trust the forwarded
        // scheme/host so Filament lazy-loaded component assets never fall back
        // to insecure http:// URLs on an https:// admin page.
        $middleware->trustProxies(
            at: env('TRUSTED_PROXIES', '*'),
            headers: Request::HEADER_X_FORWARDED_TRAEFIK,
        );
        $middleware->validateCsrfTokens(except: ['webhooks/couriers/*']);
        $middleware->appendToGroup('web', PreventDemoModeWrites::class);
        $middleware->appendToGroup('web', SetCurrentCompany::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
