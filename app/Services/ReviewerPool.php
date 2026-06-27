<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Collection;

class ReviewerPool
{
    public static function all(): Collection
    {
        return User::query()
            ->whereHas('roles', fn ($query) => $query->where('slug', UserRole::Reviewer->value))
            ->orderBy('name')
            ->orderBy('username')
            ->get();
    }
}
