<?php

namespace Tests\Unit;

use App\Enums\BundleStatus;
use App\Models\Bundle;
use App\Models\File;
use App\Services\BundleAdminService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class BundleAdminServiceTest extends TestCase
{
    private ?string $slug = null;

    protected function tearDown(): void
    {
        if ($this->slug !== null) {
            if ($bundle = Bundle::where('slug', $this->slug)->first()) {
                foreach ($bundle->files as $file) {
                    $file->delete();
                }
                $bundle->delete();
            }
            Storage::disk('uploads')->deleteDirectory($this->slug);
        }

        parent::tearDown();
    }

    public function test_revoke_sets_bundle_status(): void
    {
        $bundle = $this->createBundle();
        $service = app(BundleAdminService::class);

        $service->revoke($bundle);

        $this->assertSame(BundleStatus::Revoked, $bundle->fresh()->status);
    }

    public function test_extend_expiry_adds_days_from_current_expiry(): void
    {
        $bundle = $this->createBundle(['expires_at' => now()->addDays(5)]);
        $service = app(BundleAdminService::class);

        $service->extendExpiry($bundle, 10);

        $this->assertTrue($bundle->fresh()->expires_at->greaterThan(now()->addDays(14)));
    }

    public function test_delete_removes_database_rows_and_disk_files(): void
    {
        $bundle = $this->createBundle(['expires_at' => now()->addDay()]);
        $file = $bundle->files()->first();
        Storage::disk('uploads')->put($file->fullpath, 'content');

        $service = app(BundleAdminService::class);

        $this->assertTrue($service->delete($bundle));
        $this->assertNull(Bundle::find($bundle->id));
        $this->assertFalse(Storage::disk('uploads')->exists($file->fullpath));

        $this->slug = null;
    }

    public function test_delete_returns_false_when_upload_directory_cannot_be_removed(): void
    {
        $bundle = $this->createBundle(['expires_at' => now()->addDay()]);
        $file = $bundle->files()->first();

        Storage::fake('uploads');
        Storage::disk('uploads')->put($file->fullpath, 'content');

        Storage::shouldReceive('disk')
            ->with('uploads')
            ->andReturn($disk = \Mockery::mock());

        $disk->shouldReceive('exists')->with($bundle->slug)->andReturn(true);
        $disk->shouldReceive('deleteDirectory')->with($bundle->slug)->andReturn(false);

        $service = app(BundleAdminService::class);

        $this->assertFalse($service->delete($bundle));
        $this->assertNotNull(Bundle::find($bundle->id));
    }

    public function test_extend_expiry_from_null_starts_from_now(): void
    {
        $bundle = $this->createBundle([
            'expires_at' => null,
            'expiry' => 'forever',
        ]);
        $service = app(BundleAdminService::class);

        $service->extendExpiry($bundle, 7);

        $this->assertNotNull($bundle->fresh()->expires_at);
        $this->assertTrue($bundle->fresh()->expires_at->greaterThan(now()->addDays(6)));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createBundle(array $overrides = []): Bundle
    {
        $this->slug = 'admintest'.Str::lower(Str::random(6));

        $bundle = Bundle::create(array_merge([
            'slug' => $this->slug,
            'owner_token' => substr(sha1('owner'), 0, 15),
            'preview_token' => substr(sha1('preview'), 0, 15),
            'completed' => true,
            'expiry' => '86400',
            'expires_at' => now()->addDay(),
            'fullsize' => 7,
            'max_downloads' => 0,
            'downloads' => 0,
            'status' => BundleStatus::Draft,
        ], $overrides));

        File::create([
            'uuid' => (string) Str::uuid(),
            'bundle_id' => $bundle->id,
            'original' => 'test.txt',
            'filesize' => 7,
            'fullpath' => $bundle->slug.'/test.txt',
            'filename' => 'test.txt',
            'status' => true,
        ]);

        return $bundle;
    }
}
