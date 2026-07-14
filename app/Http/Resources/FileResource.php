<?php

namespace App\Http\Resources;

use App\Helpers\Upload;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FileResource extends JsonResource
{
    public const CONTEXT_OWNER = 'owner';

    public const CONTEXT_GUEST = 'guest';

    protected string $context = self::CONTEXT_OWNER;

    public function context(string $context): static
    {
        $this->context = $context;

        return $this;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isImage = Upload::isImageFilename((string) $this->original);
        $thumbnailUrl = null;
        $downloadUrl = null;

        $bundle = $this->relationLoaded('bundle')
            ? $this->bundle
            : $this->bundle()->first();

        if ($bundle !== null && $this->thumbnail_path) {
            $routeName = $this->context === self::CONTEXT_GUEST
                ? 'bundle.file.thumbnail'
                : 'upload.file.thumbnail';

            $parameters = [
                'bundle' => $bundle,
                'file' => $this->resource,
            ];

            if ($this->context === self::CONTEXT_GUEST && $request->query('auth')) {
                $parameters['auth'] = $request->query('auth');
            }

            $thumbnailUrl = route($routeName, $parameters);
        }

        if (
            $bundle !== null
            && $this->context === self::CONTEXT_GUEST
            && (int) $bundle->max_downloads === 0
        ) {
            $parameters = [
                'bundle' => $bundle,
                'file' => $this->resource,
            ];

            if ($request->query('auth')) {
                $parameters['auth'] = $request->query('auth');
            }

            $downloadUrl = route('bundle.file.download', $parameters);
        }

        return [
            'uuid' => $this->uuid,
            'bundle_slug' => $this->bundle_slug,
            'original' => $this->original,
            'filesize' => (int) $this->filesize,
            'fullpath' => $this->when($this->context === self::CONTEXT_OWNER, $this->fullpath),
            'filename' => $this->filename,
            'created_at' => $this->created_at,
            'status' => $this->status,
            'hash' => $this->when($this->context === self::CONTEXT_OWNER, $this->hash),
            'is_image' => $isImage,
            'thumbnail_url' => $thumbnailUrl,
            'download_url' => $downloadUrl,
        ];
    }
}
