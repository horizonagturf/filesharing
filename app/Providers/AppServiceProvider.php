<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
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

        $this->registerMailLogging();

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
