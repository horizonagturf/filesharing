<?php

namespace App\Services;

use App\Enums\ShareMode;
use App\Helpers\Upload;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SharingSettings
{
    public const KEY_DEFAULT_SHARE_MODE = 'sharing.default_share_mode';

    public const KEY_BLOCKED_EXTENSIONS = 'sharing.upload_blocked_extensions';

    public const KEY_INVITATION_REQUIRE_OTP = 'sharing.invitation_require_otp';

    public const KEY_INVITATION_OTP_DEFAULT = 'sharing.invitation_otp_default';

    public const DEFAULT_BLOCKED_EXTENSIONS = 'exe,bat,cmd,com,scr,pif,msi,dll,ps1,ps2,psc1,psc2,vbs,vbe,jse,wsf,wsh,reg,inf,cpl';

    private const CACHE_KEY = 'sharing.settings';

    public function defaultShareMode(): ShareMode
    {
        $stored = $this->get(self::KEY_DEFAULT_SHARE_MODE);

        if ($stored !== null && ($mode = ShareMode::tryFrom($stored)) !== null) {
            return $mode;
        }

        $env = config('sharing.default_share_mode', 'invitation');

        return ShareMode::tryFrom($env) ?? ShareMode::Invitation;
    }

    public function setDefaultShareMode(ShareMode $mode): void
    {
        $envDefault = ShareMode::tryFrom(config('sharing.default_share_mode', 'invitation'))
            ?? ShareMode::Invitation;

        $this->set(
            self::KEY_DEFAULT_SHARE_MODE,
            $mode === $envDefault ? null : $mode->value,
        );
    }

    public function invitationRequireOtp(): bool
    {
        $stored = $this->get(self::KEY_INVITATION_REQUIRE_OTP);

        if ($stored !== null) {
            return filter_var($stored, FILTER_VALIDATE_BOOLEAN);
        }

        return (bool) config('sharing.invitation_require_otp', true);
    }

    public function setInvitationRequireOtp(bool $required): void
    {
        $envDefault = (bool) config('sharing.invitation_require_otp', true);

        $this->set(
            self::KEY_INVITATION_REQUIRE_OTP,
            $required === $envDefault ? null : ($required ? '1' : '0'),
        );
    }

    /**
     * Whether OTP starts ticked for new bundles when it is not enforced.
     */
    public function invitationOtpDefault(): bool
    {
        $stored = $this->get(self::KEY_INVITATION_OTP_DEFAULT);

        if ($stored !== null) {
            return filter_var($stored, FILTER_VALIDATE_BOOLEAN);
        }

        return (bool) config('sharing.invitation_otp_default', true);
    }

    public function setInvitationOtpDefault(bool $default): void
    {
        $envDefault = (bool) config('sharing.invitation_otp_default', true);

        $this->set(
            self::KEY_INVITATION_OTP_DEFAULT,
            $default === $envDefault ? null : ($default ? '1' : '0'),
        );
    }

    /**
     * Blocklist from environment/config (not the admin DB override).
     *
     * @return list<string>
     */
    public function envBlockedExtensions(): array
    {
        $value = config('sharing.upload_blocked_extensions');

        if ($value === null) {
            $value = self::DEFAULT_BLOCKED_EXTENSIONS;
        }

        return Upload::parseExtensionList((string) $value);
    }

    /**
     * @return list<string>
     */
    public function blockedExtensions(): array
    {
        if ($this->hasBlockedExtensionsOverride()) {
            $stored = Setting::query()
                ->where('key', self::KEY_BLOCKED_EXTENSIONS)
                ->value('value');

            return Upload::parseExtensionList($stored ?? '');
        }

        return $this->envBlockedExtensions();
    }

    public function hasBlockedExtensionsOverride(): bool
    {
        return Setting::query()->where('key', self::KEY_BLOCKED_EXTENSIONS)->exists();
    }

    /**
     * @param  list<string>|null  $extensions
     */
    public function setBlockedExtensions(?array $extensions): void
    {
        if ($extensions === null) {
            Setting::query()->where('key', self::KEY_BLOCKED_EXTENSIONS)->delete();
            Cache::forget(self::CACHE_KEY);

            return;
        }

        $normalized = Upload::parseExtensionList(implode(',', $extensions));

        Setting::query()->updateOrCreate(
            ['key' => self::KEY_BLOCKED_EXTENSIONS],
            ['value' => implode(',', $normalized)],
        );

        Cache::forget(self::CACHE_KEY);
    }

    public function get(string $key, ?string $default = null): ?string
    {
        return $this->all()[$key] ?? $default;
    }

    public function set(string $key, ?string $value): void
    {
        if ($value === null || $value === '') {
            Setting::query()->where('key', $key)->delete();
        } else {
            Setting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value],
            );
        }

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, string|null>
     */
    public function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            $defaults = config('sharing.settings_defaults', []);
            $stored = Setting::query()
                ->whereIn('key', array_keys($defaults))
                ->pluck('value', 'key')
                ->all();

            return array_merge($defaults, $stored);
        });
    }
}
