<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('oauth', function (Request $request) {
            return Limit::perMinute(config('security.oauth_rate_limit_per_minute', 10))
                ->by($request->ip());
        });

        RateLimiter::for('download', function (Request $request) {
            return Limit::perMinute(config('security.download_rate_limit_per_minute', 30))
                ->by($request->ip());
        });

        RateLimiter::for('otp', function (Request $request) {
            return Limit::perHour(config('security.otp_route_rate_limit_per_hour', 30))
                ->by($request->ip());
        });

        if (! empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            \URL::forceScheme('https');
        }
    }
}
