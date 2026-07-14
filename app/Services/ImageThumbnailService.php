<?php

namespace App\Services;

use App\Helpers\Upload;
use App\Models\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ImageThumbnailService
{
    private const MAX_EDGE = 200;

    private const JPEG_QUALITY = 82;

    public function generate(File $file): ?string
    {
        if (! Upload::isImageFilename($file->original ?? '')) {
            return null;
        }

        if (! extension_loaded('gd') || ! function_exists('imagecreatetruecolor')) {
            Log::warning('Image thumbnail skipped: GD extension unavailable', [
                'file_uuid' => $file->uuid,
            ]);

            return null;
        }

        $disk = Storage::disk('uploads');

        if (empty($file->fullpath) || ! $disk->exists($file->fullpath)) {
            return null;
        }

        try {
            $contents = $disk->get($file->fullpath);
            if ($contents === null || $contents === '') {
                return null;
            }

            $source = @imagecreatefromstring($contents);
            if ($source === false) {
                Log::warning('Image thumbnail skipped: unable to decode image', [
                    'file_uuid' => $file->uuid,
                ]);

                return null;
            }

            $width = imagesx($source);
            $height = imagesy($source);

            if ($width < 1 || $height < 1) {
                imagedestroy($source);

                return null;
            }

            $scale = min(self::MAX_EDGE / $width, self::MAX_EDGE / $height, 1.0);
            $newWidth = max(1, (int) round($width * $scale));
            $newHeight = max(1, (int) round($height * $scale));

            $thumb = imagecreatetruecolor($newWidth, $newHeight);
            if ($thumb === false) {
                imagedestroy($source);

                return null;
            }

            $white = imagecolorallocate($thumb, 255, 255, 255);
            imagefilledrectangle($thumb, 0, 0, $newWidth, $newHeight, $white);
            imagecopyresampled($thumb, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($source);

            ob_start();
            imagejpeg($thumb, null, self::JPEG_QUALITY);
            $jpeg = ob_get_clean();
            imagedestroy($thumb);

            if ($jpeg === false || $jpeg === '') {
                return null;
            }

            $bundle = $file->relationLoaded('bundle')
                ? $file->bundle
                : $file->bundle()->first();

            if ($bundle === null) {
                return null;
            }

            $path = $bundle->slug.'/thumbs/'.$file->filename.'.jpg';

            if (! $disk->put($path, $jpeg)) {
                return null;
            }

            return $path;
        } catch (Throwable $e) {
            Log::warning('Image thumbnail generation failed', [
                'file_uuid' => $file->uuid,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function delete(?string $thumbnailPath): void
    {
        if ($thumbnailPath === null || $thumbnailPath === '') {
            return;
        }

        $disk = Storage::disk('uploads');

        if ($disk->exists($thumbnailPath)) {
            $disk->delete($thumbnailPath);
        }
    }

    public function streamResponse(File $file): Response
    {
        abort_unless(! empty($file->thumbnail_path), 404);

        $disk = Storage::disk('uploads');
        abort_unless($disk->exists($file->thumbnail_path), 404);

        return response($disk->get($file->thumbnail_path), 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
