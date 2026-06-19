<?php

use App\Http\Middleware\PreventDemoModeWrites;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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
        $middleware->appendToGroup('web', PreventDemoModeWrites::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
