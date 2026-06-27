<?php

namespace Tests\Feature;

use App\Models\Bundle;
use App\Models\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeleteFileScopeTest extends TestCase
{
    private array $slugs = [];

    protected function tearDown(): void
    {
        foreach ($this->slugs as $slug) {
            if ($bundle = Bundle::find($slug)) {
                foreach ($bundle->files as $file) {
                    $file->forceDelete();
                }
                $bundle->forceDelete();
            }
            Storage::disk('uploads')->deleteDirectory($slug);
        }

        parent::tearDown();
    }

    public function test_cannot_delete_file_from_another_bundle(): void
    {
        $bundleA = $this->createBundle('bundlea');
        $bundleB = $this->createBundle('bundleb');
        $foreignFile = $this->createFile($bundleB, 'foreign-file.txt');

        Storage::disk('uploads')->put($foreignFile->fullpath, 'content');

        $response = $this->deleteJson('/upload/'.$bundleA->slug.'/file', [
            'uuid' => $foreignFile->uuid,
            'auth' => $bundleA->owner_token,
        ], [
            'X-Upload-Auth' => $bundleA->owner_token,
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertNotFound();
        $this->assertNotNull(File::find($foreignFile->uuid));
        $this->assertTrue(Storage::disk('uploads')->exists($foreignFile->fullpath));
    }

    private function createBundle(string $slug): Bundle
    {
        $this->slugs[] = $slug;

        return Bundle::create([
            'slug' => $slug,
            'owner_token' => substr(sha1($slug.'owner'), 0, 15),
            'preview_token' => substr(sha1($slug.'preview'), 0, 15),
            'completed' => false,
            'expiry' => 86400,
            'fullsize' => 0,
            'max_downloads' => 0,
            'downloads' => 0,
        ]);
    }

    private function createFile(Bundle $bundle, string $original): File
    {
        return File::create([
            'uuid' => (string) Str::uuid(),
            'bundle_slug' => $bundle->slug,
            'original' => $original,
            'filesize' => 7,
            'fullpath' => $bundle->slug.'/'.sha1($original),
            'filename' => sha1($original),
            'created_at' => time(),
            'status' => true,
        ]);
    }
}
