<?php

use App\Http\Middleware\Authenticate;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\GuestAccess;
use App\Http\Middleware\Localisation;
use App\Http\Middleware\OwnerAccess;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\UploadAccess;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

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
            'auth' => Authenticate::class,
            'guest' => RedirectIfAuthenticated::class,
            'role' => EnsureUserHasRole::class,
            'can.upload' => UploadAccess::class,
            'access.owner' => OwnerAccess::class,
            'access.guest' => GuestAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->stopIgnoring([
            HttpException::class,
        ]);

        $exceptions->dontReportWhen(function (Throwable $e) {
            return $e instanceof HttpExceptionInterface
                && $e->getStatusCode() < 500;
        });
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('fs:bundle:purge')->everyFiveMinutes();
    })
    ->create();
