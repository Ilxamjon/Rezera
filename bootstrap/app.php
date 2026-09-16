<?php

use App\Exceptions\ApiExceptionRenderer;
use App\Http\Middleware\EnsureJsonRequest;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\OptionalSanctumAuthentication;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $minutes = max(1, (int) config('rezera.saved_searches.scheduler_minutes', 5));
        $schedule->command('saved-searches:process-alerts')->cron('*/'.$minutes.' * * * *');
        $schedule->command('saved-searches:cleanup-alerts')->daily();
        $schedule->command('subscriptions:process-lifecycle')->hourly();
        $schedule->command('reservations:expire-pending')->everyMinute();
        $schedule->command('reservations:send-reminders')->everyFiveMinutes();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            EnsureJsonRequest::class,
        ]);

        $middleware->alias([
            'platform.admin' => EnsurePlatformAdmin::class,
            'optional.sanctum' => OptionalSanctumAuthentication::class,
        ]);

        $middleware->throttleApi('api');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $throwable, Request $request) {
            return ApiExceptionRenderer::render($throwable, $request);
        });
    })
    ->create();
