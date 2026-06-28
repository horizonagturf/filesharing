<?php

namespace App\Filament\AvatarProviders;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentColor;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Spatie\Color\Rgb;

class InitialsAvatarProvider implements AvatarProvider
{
    public function get(Model|Authenticatable $record): string
    {
        $initials = str(Filament::getNameForDefaultAvatar($record))
            ->trim()
            ->explode(' ')
            ->map(fn (string $segment): string => filled($segment) ? mb_substr($segment, 0, 1) : '')
            ->join('');

        $backgroundColor = Rgb::fromString('rgb('.FilamentColor::getColors()['gray'][950].')')->toHex();

        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 128 128"><rect width="128" height="128" fill="%s"/><text x="50%%" y="50%%" dy="0.35em" text-anchor="middle" fill="#FFFFFF" font-family="system-ui,sans-serif" font-size="48" font-weight="600">%s</text></svg>',
            htmlspecialchars($backgroundColor, ENT_QUOTES),
            htmlspecialchars($initials, ENT_QUOTES),
        );

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
