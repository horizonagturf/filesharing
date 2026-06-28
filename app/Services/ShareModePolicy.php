<?php

namespace App\Services;

use App\Enums\ShareMode;
use App\Models\User;
use InvalidArgumentException;

class ShareModePolicy
{
    public function __construct(
        private readonly SharingSettings $sharingSettings,
    ) {}

    public function canUseStaticLinks(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->groups()->where('allow_static_links', true)->exists();
    }

    public function defaultShareMode(): ShareMode
    {
        return $this->sharingSettings->defaultShareMode();
    }

    public function effectiveShareMode(?User $user, ?ShareMode $mode = null): ShareMode
    {
        $mode ??= $this->defaultShareMode();

        if ($mode === ShareMode::StaticLink && ! $this->canUseStaticLinks($user)) {
            return ShareMode::Invitation;
        }

        return $mode;
    }

    public function resolveShareMode(?User $user, ?string $requested): ShareMode
    {
        if ($requested !== null && $requested !== '') {
            $mode = ShareMode::tryFrom($requested);

            if ($mode === null) {
                return $this->effectiveShareMode($user);
            }

            if ($mode === ShareMode::StaticLink && ! $this->canUseStaticLinks($user)) {
                throw new InvalidArgumentException(__('sharing.static-link-not-allowed'));
            }

            return $mode;
        }

        return $this->effectiveShareMode($user);
    }
}
