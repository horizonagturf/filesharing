<?php

namespace App\Console\Commands;

use App\Enums\BundleStatus;
use App\Enums\UserRole;
use App\Models\Bundle;
use App\Models\File;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

class MigrateOrbit extends Command
{
    protected $signature = 'fs:migrate:orbit
                            {--path= : Orbit content directory (default: storage/content)}
                            {--force : Re-import records even when they already exist in SQL}';

    protected $description = 'Import Orbit JSON flat-file data into the SQL database';

    private int $imported = 0;

    private int $skipped = 0;

    private int $failed = 0;

    public function handle(): int
    {
        $basePath = $this->option('path') ?: storage_path('content');

        if (! is_dir($basePath)) {
            $this->error("Orbit content directory not found: {$basePath}");

            return self::FAILURE;
        }

        $this->info("Importing Orbit data from {$basePath}");

        try {
            DB::transaction(function () use ($basePath) {
                $usersByUsername = $this->importUsers($basePath.'/users');
                $bundlesBySlug = $this->importBundles($basePath.'/bundles', $usersByUsername);
                $this->importFiles($basePath.'/files', $bundlesBySlug);
            });
        } catch (Exception $e) {
            $this->error($e->getMessage());
            $this->failed++;

            return self::FAILURE;
        }

        $this->newLine();
        $this->info("Import complete: {$this->imported} imported, {$this->skipped} skipped, {$this->failed} failed");

        return self::SUCCESS;
    }

    /**
     * @return array<string, int>
     */
    private function importUsers(string $directory): array
    {
        $map = [];

        foreach ($this->jsonFiles($directory) as $file) {
            $data = $this->readJson($file);
            if ($data === null) {
                continue;
            }

            $username = $data['username'] ?? pathinfo($file->getFilename(), PATHINFO_FILENAME);
            if ($username === '') {
                $this->logFailed('user', $file->getPathname(), 'missing username');

                continue;
            }

            $existing = User::where('username', $username)->first();
            if ($existing && ! $this->option('force')) {
                $map[$username] = $existing->id;
                $this->logSkipped('user', $username);

                continue;
            }

            $user = $existing ?? new User;
            $user->fill([
                'username' => $username,
                'password' => $data['password'] ?? '',
                'last_login_at' => $this->parseTimestamp($data['connected_at'] ?? null),
            ]);
            $user->save();

            if (! $existing) {
                $user->assignRole(UserRole::User);
            }

            $map[$username] = $user->id;
            $this->logImported('user', $username);
        }

        return $map;
    }

    /**
     * @param  array<string, int>  $usersByUsername
     * @return array<string, int>
     */
    private function importBundles(string $directory, array $usersByUsername): array
    {
        $map = [];

        foreach ($this->jsonFiles($directory) as $file) {
            $data = $this->readJson($file);
            if ($data === null) {
                continue;
            }

            $slug = $data['slug'] ?? pathinfo($file->getFilename(), PATHINFO_FILENAME);
            if ($slug === '') {
                $this->logFailed('bundle', $file->getPathname(), 'missing slug');

                continue;
            }

            $existing = Bundle::where('slug', $slug)->first();
            if ($existing && ! $this->option('force')) {
                $map[$slug] = $existing->id;
                $this->logSkipped('bundle', $slug);

                continue;
            }

            $userId = null;
            if (! empty($data['user_username'])) {
                $userId = $usersByUsername[$data['user_username']] ?? User::where('username', $data['user_username'])->value('id');
                if ($userId === null) {
                    $this->warn("Bundle {$slug}: user \"{$data['user_username']}\" not found, importing without owner");
                }
            }

            $completed = (bool) ($data['completed'] ?? false);
            $status = $completed ? BundleStatus::Sent : BundleStatus::Draft;

            $bundle = $existing ?? new Bundle;
            $bundle->fill([
                'slug' => $slug,
                'user_id' => $userId,
                'title' => $data['title'] ?? null,
                'description' => $data['description'] ?? null,
                'password' => $data['password'] ?? null,
                'owner_token' => $data['owner_token'] ?? substr(sha1($slug), 0, 15),
                'preview_token' => $data['preview_token'] ?? substr(sha1($slug.'preview'), 0, 15),
                'fullsize' => (int) ($data['fullsize'] ?? 0),
                'max_downloads' => isset($data['max_downloads']) ? (int) $data['max_downloads'] : null,
                'downloads' => (int) ($data['downloads'] ?? 0),
                'completed' => $completed,
                'status' => $status,
                'expiry' => (string) ($data['expiry'] ?? '86400'),
                'expires_at' => $this->parseTimestamp($data['expires_at'] ?? null),
                'preview_link' => $data['preview_link'] ?? null,
                'download_link' => $data['download_link'] ?? null,
                'deletion_link' => $data['deletion_link'] ?? null,
            ]);
            $bundle->save();

            $map[$slug] = $bundle->id;
            $this->logImported('bundle', $slug);
        }

        return $map;
    }

    /**
     * @param  array<string, int>  $bundlesBySlug
     */
    private function importFiles(string $directory, array $bundlesBySlug): void
    {
        foreach ($this->jsonFiles($directory) as $file) {
            $data = $this->readJson($file);
            if ($data === null) {
                continue;
            }

            $uuid = $data['uuid'] ?? pathinfo($file->getFilename(), PATHINFO_FILENAME);
            if ($uuid === '') {
                $this->logFailed('file', $file->getPathname(), 'missing uuid');

                continue;
            }

            if (File::where('uuid', $uuid)->exists() && ! $this->option('force')) {
                $this->logSkipped('file', $uuid);

                continue;
            }

            $bundleSlug = $data['bundle_slug'] ?? null;
            if ($bundleSlug === null) {
                $this->logFailed('file', $uuid, 'missing bundle_slug');

                continue;
            }

            $bundleId = $bundlesBySlug[$bundleSlug] ?? Bundle::where('slug', $bundleSlug)->value('id');
            if ($bundleId === null) {
                $this->logFailed('file', $uuid, "bundle \"{$bundleSlug}\" not found");

                continue;
            }

            $record = File::firstOrNew(['uuid' => $uuid]);
            $record->fill([
                'bundle_id' => $bundleId,
                'original' => $data['original'] ?? null,
                'filename' => $data['filename'] ?? null,
                'fullpath' => $data['fullpath'] ?? null,
                'filesize' => (int) ($data['filesize'] ?? 0),
                'status' => (bool) ($data['status'] ?? true),
                'hash' => $data['hash'] ?? null,
            ]);
            $record->save();

            $this->logImported('file', $uuid);
        }
    }

    /**
     * @return iterable<SplFileInfo>
     */
    private function jsonFiles(string $directory): iterable
    {
        if (! is_dir($directory)) {
            $this->warn("Directory not found, skipping: {$directory}");

            return [];
        }

        return Finder::create()->files()->name('*.json')->in($directory)->sortByName();
    }

    private function readJson(SplFileInfo $file): ?array
    {
        $contents = file_get_contents($file->getPathname());
        if ($contents === false || trim($contents) === '') {
            $this->logFailed('record', $file->getPathname(), 'empty or unreadable file');

            return null;
        }

        $data = json_decode($contents, true);
        if (! is_array($data)) {
            $this->logFailed('record', $file->getPathname(), 'invalid JSON');

            return null;
        }

        return $data;
    }

    private function parseTimestamp(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return Carbon::createFromTimestamp((int) $value);
        }

        return Carbon::parse($value);
    }

    private function logImported(string $type, string $key): void
    {
        $this->imported++;
        $this->line("  <info>imported</info> {$type} {$key}");
    }

    private function logSkipped(string $type, string $key): void
    {
        $this->skipped++;
        $this->line("  <comment>skipped</comment> {$type} {$key} (already in SQL)");
    }

    private function logFailed(string $type, string $key, string $reason): void
    {
        $this->failed++;
        $this->line("  <error>failed</error> {$type} {$key}: {$reason}");
    }
}
