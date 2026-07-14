<?php

namespace Tests\Feature;

use App\Enums\BundleStatus;
use App\Enums\ShareMode;
use App\Models\Bundle;
use App\Models\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class OwnerDeleteBundleTest extends TestCase
{
    private ?string $slug = null;

    protected function tearDown(): void
    {
        if ($this->slug !== null) {
            Storage::disk('uploads')->deleteDirectory($this->slug);
        }

        parent::tearDown();
    }

    public function test_owner_delete_removes_disk_directory_and_database_rows(): void
    {
        $slug = 'owner-delete-'.Str::random(6);
        $this->slug = $slug;

        $bundle = Bundle::create([
            'slug' => $slug,
            'owner_token' => substr(sha1('owner-'.$slug), 0, 15),
            'preview_token' => substr(sha1('preview-'.$slug), 0, 15),
            'share_mode' => ShareMode::StaticLink,
            'completed' => false,
            'status' => BundleStatus::Draft,
            'expiry' => '86400',
            'fullsize' => 0,
            'max_downloads' => 0,
            'downloads' => 0,
        ]);

        $filename = (string) Str::uuid();
        $fullpath = "{$slug}/{$filename}";
        Storage::disk('uploads')->put($fullpath, 'secret-content');
        Storage::disk('uploads')->put("{$slug}/thumbs/{$filename}.jpg", 'thumb');

        $file = File::create([
            'uuid' => (string) Str::uuid(),
            'bundle_id' => $bundle->id,
            'original' => 'notes.txt',
            'filesize' => 13,
            'fullpath' => $fullpath,
            'filename' => $filename,
            'thumbnail_path' => "{$slug}/thumbs/{$filename}.jpg",
            'status' => true,
        ]);

        $this->assertTrue(Storage::disk('uploads')->exists($fullpath));

        $response = $this->deleteJson(
            route('upload.bundle.delete', ['bundle' => $bundle]),
            ['auth' => $bundle->owner_token],
            [
                'X-Upload-Auth' => $bundle->owner_token,
                'X-Requested-With' => 'XMLHttpRequest',
            ]
        );

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertNull(Bundle::find($bundle->id));
        $this->assertNull(File::find($file->id));
        $this->assertFalse(Storage::disk('uploads')->exists($fullpath));
        $this->assertFalse(Storage::disk('uploads')->exists($slug));

        $this->slug = null;
    }
}
