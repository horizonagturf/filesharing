<?php

namespace App\Services;

use App\Models\User;

class ApprovalPolicy
{
    public function requiresApproval(User $user): bool
    {
        if ($user->requires_approval !== null) {
            return $user->requires_approval;
        }

        if ($user->groups()->where('requires_approval', true)->exists()) {
            return true;
        }

        return (bool) config('approval.required_default');
    }
}
