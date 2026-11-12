<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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

        RateLimiter::for('login', function (Request $request) {
            $perAccount = max(1, config('security.login_rate_limit_per_minute', 5));
            $username = Str::lower(trim((string) $request->input('login')));
            $tooMany = fn (Request $request, array $headers) => response()->json([
                'result' => false,
                'message' => __('auth.throttle', [
                    'seconds' => $headers['Retry-After'] ?? 60,
                ]),
            ], 429, $headers);

            return [
                Limit::perMinute($perAccount)
                    ->by('login:'.$username.'|'.$request->ip())
                    ->response($tooMany),
                Limit::perMinute($perAccount * 4)
                    ->by('login-ip:'.$request->ip())
                    ->response($tooMany),
            ];
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
