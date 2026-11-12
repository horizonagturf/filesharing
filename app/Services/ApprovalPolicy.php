<?php

namespace App\Services;

use App\Models\Bundle;
use App\Models\User;

class ApprovalPolicy
{
    /**
     * Approval is decided by who owns the bundle, never by whoever happens
     * to be signed in when it is completed.
     */
    public function requiresApprovalForBundle(Bundle $bundle): bool
    {
        $owner = $bundle->user;

        if ($owner === null) {
            return (bool) config('approval.required_anonymous');
        }

        return $this->requiresApproval($owner);
    }

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
