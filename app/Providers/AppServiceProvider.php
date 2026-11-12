<?php

namespace App\Providers;

use App\Services\BrandingSettings;
use App\Services\SystemHealth;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
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

        $this->registerMailLogging();

        Event::listen(Looping::class, fn () => SystemHealth::recordQueueHeartbeat());

        if (! empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            \URL::forceScheme('https');
        }
    }

    /**
     * Log the full mail lifecycle (queued mail is sent by a worker, so a request
     * "succeeding" says nothing about delivery). Bodies and OTP codes are never logged.
     */
    private function registerMailLogging(): void
    {
        $recipients = static fn ($message): array => collect($message->getTo())
            ->map(fn ($address) => $address->getAddress())
            ->all();

        Event::listen(MessageSending::class, function (MessageSending $event) use ($recipients) {
            Log::info('Mail: handing message to transport', [
                'mailer' => config('mail.default'),
                'host' => config('mail.mailers.'.config('mail.default').'.host'),
                'to' => $recipients($event->message),
                'subject' => $event->message->getSubject(),
            ]);
        });

        Event::listen(MessageSent::class, function (MessageSent $event) use ($recipients) {
            Log::info('Mail: transport accepted message', [
                'mailer' => config('mail.default'),
                'to' => $recipients($event->message),
                'subject' => $event->message->getSubject(),
                'message_id' => $event->sent->getMessageId(),
            ]);
        });

        $mailJobContext = static function ($event): ?array {
            if ($event->job->resolveName() !== SendQueuedMailable::class) {
                return null;
            }

            return [
                'mailable' => $event->job->payload()['displayName'] ?? null,
                'job_id' => $event->job->getJobId(),
                'attempt' => $event->job->attempts(),
                'exception' => $event->exception::class,
                'error' => $event->exception->getMessage(),
            ];
        };

        Event::listen(JobExceptionOccurred::class, function (JobExceptionOccurred $event) use ($mailJobContext) {
            if ($context = $mailJobContext($event)) {
                Log::warning('Mail: send attempt failed (may retry)', $context);
            }
        });

        Event::listen(JobFailed::class, function (JobFailed $event) use ($mailJobContext) {
            if ($context = $mailJobContext($event)) {
                Log::error('Mail: send permanently failed', $context);
            }
        });
    }
}
