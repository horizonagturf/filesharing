<?php

namespace App\Services;

use App\Enums\ShareMode;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SharingSettings
{
    public const KEY_DEFAULT_SHARE_MODE = 'sharing.default_share_mode';

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
