<?php

namespace Tests\Unit;

use App\Models\Bundle;
use App\Models\File;
use App\Models\User;
use App\Services\ImageThumbnailService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ImageThumbnailServiceTest extends TestCase
{
    private ImageThumbnailService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ImageThumbnailService::class);
        Storage::fake('uploads');
    }

    public function test_generates_jpeg_thumbnail_for_png(): void
    {
        $file = $this->createImageFile('photo.png');
        $this->putPng($file->fullpath, 320, 240);

        $path = $this->service->generate($file);

        $this->assertNotNull($path);
        $this->assertSame($file->bundle->slug.'/thumbs/'.$file->filename.'.jpg', $path);
        Storage::disk('uploads')->assertExists($path);

        $thumb = imagecreatefromstring(Storage::disk('uploads')->get($path));
        $this->assertNotFalse($thumb);
        $this->assertLessThanOrEqual(200, imagesx($thumb));
        $this->assertLessThanOrEqual(200, imagesy($thumb));
        imagedestroy($thumb);
    }

    public function test_generates_jpeg_thumbnail_for_jpeg(): void
    {
        $file = $this->createImageFile('photo.jpg');
        $this->putJpeg($file->fullpath, 400, 100);

        $path = $this->service->generate($file);

        $this->assertNotNull($path);
        Storage::disk('uploads')->assertExists($path);

        $thumb = imagecreatefromstring(Storage::disk('uploads')->get($path));
        $this->assertSame(200, imagesx($thumb));
        $this->assertSame(50, imagesy($thumb));
        imagedestroy($thumb);
    }

    public function test_returns_null_for_non_image_extension(): void
    {
        $file = $this->createImageFile('document.txt', original: 'document.txt');
        Storage::disk('uploads')->put($file->fullpath, 'not an image');

        $this->assertNull($this->service->generate($file));
    }

    public function test_deletes_thumbnail_file(): void
    {
        $file = $this->createImageFile('photo.png');
        $this->putPng($file->fullpath, 100, 100);

        $path = $this->service->generate($file);
        $this->assertNotNull($path);
        Storage::disk('uploads')->assertExists($path);

        $this->service->delete($path);

        Storage::disk('uploads')->assertMissing($path);
    }

    public function test_delete_ignores_null_and_missing_paths(): void
    {
        $this->service->delete(null);
        $this->service->delete('');
        $this->service->delete('missing/thumbs/gone.jpg');

        $this->assertTrue(true);
    }

    private function createImageFile(string $filename, ?string $original = null): File
    {
        $user = User::factory()->create();
        $slug = 'thumb-'.Str::lower(Str::random(8));

        $bundle = Bundle::create([
            'user_id' => $user->id,
            'slug' => $slug,
            'title' => 'Thumb test',
            'owner_token' => substr(sha1($slug.'owner'), 0, 15),
            'preview_token' => substr(sha1($slug.'preview'), 0, 15),
            'completed' => false,
            'expiry' => '86400',
            'fullsize' => 0,
            'max_downloads' => 0,
            'downloads' => 0,
        ]);

        $file = File::create([
            'uuid' => (string) Str::uuid(),
            'bundle_id' => $bundle->id,
            'original' => $original ?? $filename,
            'filesize' => 100,
            'fullpath' => $slug.'/'.$filename,
            'filename' => pathinfo($filename, PATHINFO_FILENAME) ?: 'file',
            'status' => true,
        ]);
        $file->setRelation('bundle', $bundle);

        return $file;
    }

    private function putPng(string $path, int $width, int $height): void
    {
        $image = imagecreatetruecolor($width, $height);
        $color = imagecolorallocate($image, 30, 144, 255);
        imagefilledrectangle($image, 0, 0, $width, $height, $color);
        ob_start();
        imagepng($image);
        $contents = ob_get_clean();
        imagedestroy($image);

        Storage::disk('uploads')->put($path, $contents);
    }

    private function putJpeg(string $path, int $width, int $height): void
    {
        $image = imagecreatetruecolor($width, $height);
        $color = imagecolorallocate($image, 220, 20, 60);
        imagefilledrectangle($image, 0, 0, $width, $height, $color);
        ob_start();
        imagejpeg($image, null, 90);
        $contents = ob_get_clean();
        imagedestroy($image);

        Storage::disk('uploads')->put($path, $contents);
    }
}
