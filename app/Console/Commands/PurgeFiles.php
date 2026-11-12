<?php

namespace App\Console\Commands;

use App\Enums\AuditEvent;
use App\Enums\BundleStatus;
use App\Models\Bundle;
use App\Services\Audit;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PurgeFiles extends Command
{
    protected $signature = 'fs:bundle:purge';

    protected $description = 'Purge expired bundles, abandoned drafts and old denied bundles from storage';

    public function handle(): int
    {
        $purged = 0;

        $purged += $this->purge('expired', Bundle::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()));

        $draftHours = (int) config('sharing.draft_retention_hours', 72);
        if ($draftHours > 0) {
            // Never completed. Bundles completed before the approval workflow
            // also have draft status but completed = true, and are live shares.
            $purged += $this->purge('abandoned_draft', $this->inactiveSince(
                Bundle::query()
                    ->where('completed', false)
                    ->where('status', BundleStatus::Draft),
                now()->subHours($draftHours),
            ));
        }

        $deniedDays = (int) config('sharing.denied_retention_days', 14);
        if ($deniedDays > 0) {
            $purged += $this->purge('denied', $this->inactiveSince(
                Bundle::query()->where('status', BundleStatus::Denied),
                now()->subDays($deniedDays),
            ));
        }

        $this->info("Purged {$purged} bundle(s).");

        return self::SUCCESS;
    }

    /**
     * Uploading files doesn't touch the bundle row, so a bundle only counts as
     * inactive when neither it nor any of its files changed since the cutoff.
     */
    private function inactiveSince(Builder $query, CarbonInterface $cutoff): Builder
    {
        return $query
            ->where('updated_at', '<', $cutoff)
            ->whereDoesntHave('files', fn (Builder $files) => $files->where('created_at', '>=', $cutoff));
    }

    private function purge(string $reason, Builder $query): int
    {
        $count = 0;

        $query->with('files')->chunkById(100, function ($bundles) use ($reason, &$count) {
            foreach ($bundles as $bundle) {
                try {
                    if ($this->purgeBundle($bundle, $reason)) {
                        $count++;
                    }
                } catch (Throwable $e) {
                    // One bad bundle must not stop the rest of the run.
                    report($e);
                    $this->error("-> {$bundle->slug}: {$e->getMessage()}");
                }
            }
        });

        return $count;
    }

    private function purgeBundle(Bundle $bundle, string $reason): bool
    {
        $this->line("Found bundle {$bundle->slug} ({$reason})");

        $uploads = Storage::disk('uploads');
        $storageRemoved = ! $uploads->exists($bundle->slug)
            || $uploads->deleteDirectory($bundle->slug);

        if (! $storageRemoved) {
            $this->error('-> upload directory could not be deleted, keeping bundle for retry');

            return false;
        }

        Audit::log(AuditEvent::BundlePurged, [
            'bundle' => $bundle,
            'actor_type' => 'system',
            'metadata' => [
                'reason' => $reason,
                'slug' => $bundle->slug,
                'status' => $bundle->status?->value,
                'files' => $bundle->files->count(),
                'fullsize' => $bundle->fullsize,
            ],
        ]);

        foreach ($bundle->files as $file) {
            $file->delete();
        }

        $bundle->delete();

        $this->info('-> bundle was properly deleted');

        return true;
    }
}
