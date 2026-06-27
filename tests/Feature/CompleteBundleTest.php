<?php

namespace Tests\Feature;

use App\Models\Bundle;
use App\Models\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class CompleteBundleTest extends TestCase
{
    public function test_complete_bundle_marks_bundle_completed_and_persists_metadata(): void
    {
        $bundle = Bundle::create([
            'slug' => 'completebundle',
            'owner_token' => substr(sha1('complete-owner'), 0, 15),
            'preview_token' => substr(sha1('complete-preview'), 0, 15),
            'completed' => false,
            'expiry' => '86400',
            'fullsize' => 0,
            'max_downloads' => 0,
            'downloads' => 0,
        ]);

        File::create([
            'uuid' => (string) Str::uuid(),
            'bundle_id' => $bundle->id,
            'original' => 'first.txt',
            'filesize' => 11,
            'fullpath' => $bundle->slug.'/first',
            'filename' => 'first',
            'status' => true,
        ]);

        File::create([
            'uuid' => (string) Str::uuid(),
            'bundle_id' => $bundle->id,
            'original' => 'second.txt',
            'filesize' => 17,
            'fullpath' => $bundle->slug.'/second',
            'filename' => 'second',
            'status' => true,
        ]);

        $response = $this->postJson('/upload/'.$bundle->slug.'/complete', [
            'auth' => $bundle->owner_token,
        ], [
            'X-Upload-Auth' => $bundle->owner_token,
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertOk();

        $bundle->refresh();

        $this->assertTrue($bundle->completed);
        $this->assertSame(28, $bundle->fullsize);
        $this->assertNotNull($bundle->expires_at);
        $this->assertTrue($bundle->expires_at->isFuture());
        $this->assertSame(
            route('bundle.preview', ['bundle' => $bundle, 'auth' => $bundle->preview_token]),
            $bundle->preview_link
        );
        $this->assertSame(
            route('bundle.zip.download', ['bundle' => $bundle, 'auth' => $bundle->preview_token]),
            $bundle->download_link
        );
        $this->assertSame(
            route('upload.bundle.delete', ['bundle' => $bundle]),
            $bundle->deletion_link
        );
    }
}
