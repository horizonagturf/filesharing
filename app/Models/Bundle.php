<?php

namespace App\Models;

use App\Enums\BundleStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bundle extends Model
{
    protected $fillable = [
        'user_id',
        'slug',
        'title',
        'description',
        'password',
        'owner_token',
        'preview_token',
        'fullsize',
        'max_downloads',
        'downloads',
        'completed',
        'status',
        'expiry',
        'expires_at',
        'preview_link',
        'download_link',
        'deletion_link',
    ];

    protected function casts(): array
    {
        return [
            'completed' => 'boolean',
            'status' => BundleStatus::class,
            'expires_at' => 'datetime',
            'fullsize' => 'integer',
            'max_downloads' => 'integer',
            'downloads' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function files(): HasMany
    {
        return $this->hasMany(File::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
