<?php

namespace Tests\Feature;

use App\Models\Bundle;
use App\Models\File;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PurgeExpiredBundlesTest extends TestCase
{
    private ?string $slug = null;

    protected function tearDown(): void
    {
        if ($this->slug !== null) {
            if ($bundle = Bundle::find($this->slug)) {
                foreach ($bundle->files as $file) {
                    $file->forceDelete();
                }
                $bundle->forceDelete();
            }
            Storage::disk('uploads')->deleteDirectory($this->slug);
        }

        parent::tearDown();
    }

    public function test_purge_removes_expired_bundle_and_uploads(): void
    {
        $this->slug = 'expiredbundle';

        $bundle = Bundle::create([
            'slug' => $this->slug,
            'owner_token' => substr(sha1('owner'), 0, 15),
            'preview_token' => substr(sha1('preview'), 0, 15),
            'completed' => true,
            'expiry' => 86400,
            'expires_at' => now()->subDay(),
            'fullsize' => 7,
            'max_downloads' => 0,
            'downloads' => 0,
        ]);

        $file = File::create([
            'uuid' => (string) Str::uuid(),
            'bundle_slug' => $bundle->slug,
            'original' => 'expired.txt',
            'filesize' => 7,
            'fullpath' => $bundle->slug.'/expired.txt',
            'filename' => 'expired.txt',
            'created_at' => time(),
            'status' => true,
        ]);

        Storage::disk('uploads')->put($file->fullpath, 'expired');

        Artisan::call('fs:bundle:purge');

        $this->assertNull(Bundle::find($bundle->slug));
        $this->assertNull(File::find($file->uuid));
        $this->assertFalse(Storage::disk('uploads')->exists($file->fullpath));

        $this->slug = null;
    }

    public function test_purge_skips_bundles_without_expiry(): void
    {
        $this->slug = 'foreverbundle';

        $bundle = Bundle::create([
            'slug' => $this->slug,
            'owner_token' => substr(sha1('owner2'), 0, 15),
            'preview_token' => substr(sha1('preview2'), 0, 15),
            'completed' => true,
            'expiry' => 'forever',
            'expires_at' => null,
            'fullsize' => 0,
            'max_downloads' => 0,
            'downloads' => 0,
        ]);

        Artisan::call('fs:bundle:purge');

        $this->assertNotNull(Bundle::find($bundle->slug));
    }
}
