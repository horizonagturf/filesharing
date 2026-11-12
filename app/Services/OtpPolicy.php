<?php

namespace App\Services;

use App\Enums\ShareMode;
use App\Models\User;
use InvalidArgumentException;

class OtpPolicy
{
    public function __construct(
        private readonly SharingSettings $sharingSettings,
    ) {}

    /**
     * Whether OTP is mandatory org-wide (only allowed groups may skip it).
     */
    public function isEnforced(): bool
    {
        return $this->sharingSettings->invitationRequireOtp();
    }

    /**
     * Initial OTP value for new bundles: on when enforced, otherwise the org's default-on setting.
     */
    public function defaultRequireOtp(): bool
    {
        return $this->isEnforced() || $this->sharingSettings->invitationOtpDefault();
    }

    public function canSkipOtp(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->groups()->where('allow_invitation_without_otp', true)->exists();
    }

    public function canChooseOtpSetting(?User $user): bool
    {
        return $this->canSkipOtp($user) || ! $this->isEnforced();
    }

    public function effectiveRequireOtp(?User $user, ?bool $requested = null, ?ShareMode $shareMode = null): bool
    {
        if ($shareMode === ShareMode::StaticLink) {
            return true;
        }

        $requested ??= $this->defaultRequireOtp();

        if ($requested === false && ! $this->canSkipOtp($user) && $this->isEnforced()) {
            return true;
        }

        return $requested;
    }

    public function resolveRequireOtp(?User $user, ?bool $requested): bool
    {
        if ($requested === false && ! $this->canSkipOtp($user) && $this->isEnforced()) {
            throw new InvalidArgumentException(__('sharing.otp-skip-not-allowed'));
        }

        return $requested ?? $this->defaultRequireOtp();
    }
}
