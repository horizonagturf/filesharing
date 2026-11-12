<?php

namespace App\Providers;

use App\Services\BrandingSettings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mime\Address;
use Throwable;

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

        // Admin-configurable sender identity (Branding settings) overrides env defaults.
        Event::listen(MessageSending::class, function (MessageSending $event): void {
            try {
                $branding = app(BrandingSettings::class);
                $name = $branding->mailFromName();
                $address = $branding->mailFromAddress();
            } catch (Throwable) {
                return;
            }

            if ($name === null && $address === null) {
                return;
            }

            $current = $event->message->getFrom()[0] ?? null;
            $event->message->from(new Address(
                $address ?? $current?->getAddress() ?? config('mail.from.address'),
                $name ?? $current?->getName() ?? (string) config('mail.from.name'),
            ));
        });

        if (! empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            \URL::forceScheme('https');
        }
    }
}
