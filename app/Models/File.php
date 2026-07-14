<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class File extends Model
{
    protected $fillable = [
        'uuid',
        'bundle_id',
        'original',
        'filesize',
        'fullpath',
        'filename',
        'status',
        'hash',
        'thumbnail_path',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'filesize' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(Bundle::class);
    }

    protected function bundleSlug(): Attribute
    {
        return Attribute::get(fn () => $this->relationLoaded('bundle')
            ? $this->bundle?->slug
            : $this->bundle()->value('slug'));
    }
}
