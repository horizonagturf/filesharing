<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Detects a stopped scheduler or queue worker using heartbeats written to the cache
 * and, for the database queue, the age of the oldest waiting job.
 */
class SystemHealth
{
    public const SCHEDULER_KEY = 'health:scheduler:last-run';

    public const QUEUE_KEY = 'health:queue:last-poll';

    /** Minutes without a heartbeat before we raise an alert. */
    public const STALE_AFTER_MINUTES = 5;

    public static function recordSchedulerHeartbeat(): void
    {
        Cache::forever(self::SCHEDULER_KEY, now()->timestamp);
    }

    public static function recordQueueHeartbeat(): void
    {
        // Looping fires every few seconds on a long-running worker; avoid hammering the cache.
        if (Cache::add(self::QUEUE_KEY.':throttle', 1, 30)) {
            Cache::forever(self::QUEUE_KEY, now()->timestamp);
        }
    }

    /**
     * @return array<int, array{title: string, detail: string, level: string}>
     */
    public function alerts(): array
    {
        return array_values(array_filter([
            $this->schedulerAlert(),
            $this->queueAlert(),
            $this->failedJobsAlert(),
        ]));
    }

    private function schedulerAlert(): ?array
    {
        $last = $this->heartbeat(self::SCHEDULER_KEY);

        if ($last && $last->gt(now()->subMinutes(self::STALE_AFTER_MINUTES))) {
            return null;
        }

        return [
            'level' => 'danger',
            'title' => 'The scheduler does not appear to be running',
            'detail' => ($last
                    ? 'Last run '.$last->diffForHumans().'.'
                    : 'No run has been recorded yet.')
                .' Scheduled tasks such as expiring bundles will not run. Check that `php artisan schedule:run` is being called every minute (cron).',
        ];
    }

    private function queueAlert(): ?array
    {
        if (config('queue.default') === 'sync') {
            return null;
        }

        $threshold = now()->subMinutes(self::STALE_AFTER_MINUTES);
        $oldest = $this->oldestWaitingJob();

        if ($oldest && $oldest->lt($threshold)) {
            return [
                'level' => 'danger',
                'title' => 'The queue is not being processed',
                'detail' => 'A job has been waiting since '.$oldest->diffForHumans().'. Emails (invitations, one-time codes, approvals) are not being sent. Check the queue worker and storage/logs/queue-worker.log.',
            ];
        }

        $last = $this->heartbeat(self::QUEUE_KEY);

        if (! $last || $last->lt($threshold)) {
            return [
                'level' => 'danger',
                'title' => 'The queue worker does not appear to be running',
                'detail' => ($last
                        ? 'Last worker activity '.$last->diffForHumans().'.'
                        : 'No worker activity has been recorded yet.')
                    .' Emails will not be sent until a worker is running (`php artisan queue:work`).',
            ];
        }

        return null;
    }

    private function failedJobsAlert(): ?array
    {
        if (! Schema::hasTable('failed_jobs')) {
            return null;
        }

        $count = DB::table('failed_jobs')->count();

        if ($count === 0) {
            return null;
        }

        return [
            'level' => 'warning',
            'title' => $count.' failed queue '.str('job')->plural($count),
            'detail' => 'These usually mean an email could not be sent. See the application log for "Mail: send permanently failed", then retry with `php artisan queue:retry all` or clear with `php artisan queue:flush`.',
        ];
    }

    private function oldestWaitingJob(): ?Carbon
    {
        if (config('queue.default') !== 'database' || ! Schema::hasTable('jobs')) {
            return null;
        }

        $timestamp = DB::table('jobs')
            ->whereNull('reserved_at')
            ->min('available_at');

        return $timestamp ? Carbon::createFromTimestamp($timestamp) : null;
    }

    private function heartbeat(string $key): ?Carbon
    {
        $value = Cache::get($key);

        return $value ? Carbon::createFromTimestamp((int) $value) : null;
    }
}
