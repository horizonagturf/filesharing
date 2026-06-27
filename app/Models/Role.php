<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    protected $fillable = [
        'slug',
        'name',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function scopeWhereSlug(Builder $query, UserRole $role): Builder
    {
        return $query->where('slug', $role->value);
    }

    public static function idFor(UserRole $role): int
    {
        return static::query()
            ->where('slug', $role->value)
            ->value('id');
    }
}
