<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Group extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'requires_approval',
        'allow_static_links',
    ];

    protected function casts(): array
    {
        return [
            'requires_approval' => 'boolean',
            'allow_static_links' => 'boolean',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
