<?php

use App\Http\Middleware\GuestAccess;
use App\Http\Middleware\Localisation;
use App\Http\Middleware\OwnerAccess;
use App\Http\Middleware\UploadAccess;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(Localisation::class);

        $middleware->alias([
            'can.upload' => UploadAccess::class,
            'access.owner' => OwnerAccess::class,
            'access.guest' => GuestAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('fs:bundle:purge')->everyFiveMinutes();
    })
    ->create();
