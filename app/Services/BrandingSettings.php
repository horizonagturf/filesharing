<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class BrandingSettings
{
    public const KEY_APP_NAME = 'branding.app_name';

    public const KEY_LOGO_PATH = 'branding.logo_path';

    public const KEY_PRIMARY_COLOR = 'branding.primary_color';

    public const KEY_ACCENT_COLOR = 'branding.accent_color';

    public const KEY_FOOTER_TEXT = 'branding.footer_text';

    public const KEY_TOS_URL = 'branding.tos_url';

    public const KEY_PRIVACY_URL = 'branding.privacy_url';

    public const KEY_SHOW_CREDIT = 'branding.show_credit';

    public const DEFAULT_LOGO_PATH = 'images/logo.svg';

    private const CACHE_KEY = 'branding.settings';

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
            $defaults = config('branding.defaults', []);
            $stored = Setting::query()
                ->whereIn('key', array_keys($defaults))
                ->pluck('value', 'key')
                ->all();

            return array_merge($defaults, $stored);
        });
    }

    public function appName(): string
    {
        return $this->get(self::KEY_APP_NAME) ?? config('app.name');
    }

    public function showCreditFooter(): bool
    {
        $value = $this->get(self::KEY_SHOW_CREDIT);

        if ($value === null || $value === '') {
            return (bool) config('branding.show_credit', true);
        }

        return $value !== '0';
    }

    public function logoUrl(): string
    {
        $path = $this->get(self::KEY_LOGO_PATH);

        if ($path !== null && $path !== '') {
            return asset('storage/'.$path);
        }

        return asset(self::DEFAULT_LOGO_PATH);
    }

    /**
     * @return array<string, string>
     */
    public function cssVariables(): array
    {
        $primary = $this->get(self::KEY_PRIMARY_COLOR, '#7e22ce');
        $accent = $this->get(self::KEY_ACCENT_COLOR, '#9333ea');

        return [
            '--color-primary' => $this->hexToRgbChannels($primary),
            '--color-primary-light' => $this->hexToRgbChannels($accent),
            '--color-primary-superlight' => $this->hexToRgbChannels($this->lightenHex($accent, 0.45)),
        ];
    }

    public function hexToRgbChannels(string $hex): string
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        return "{$r} {$g} {$b}";
    }

    private function lightenHex(string $hex, float $amount): string
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        $r = min(255, (int) round(hexdec(substr($hex, 0, 2)) + (255 * $amount)));
        $g = min(255, (int) round(hexdec(substr($hex, 2, 2)) + (255 * $amount)));
        $b = min(255, (int) round(hexdec(substr($hex, 4, 2)) + (255 * $amount)));

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
}
