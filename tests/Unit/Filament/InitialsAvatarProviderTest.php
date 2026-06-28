<?php

namespace Tests\Unit\Filament;

use App\Filament\AvatarProviders\InitialsAvatarProvider;
use App\Models\User;
use Filament\Facades\Filament;
use Tests\TestCase;

class InitialsAvatarProviderTest extends TestCase
{
    public function test_returns_csp_safe_data_uri_with_initials(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $user = User::factory()->create(['name' => 'Darren Wiebe']);

        $url = app(InitialsAvatarProvider::class)->get($user);

        $this->assertStringStartsWith('data:image/svg+xml;base64,', $url);
        $this->assertStringNotContainsString('ui-avatars.com', $url);

        $svg = base64_decode(substr($url, strlen('data:image/svg+xml;base64,')));
        $this->assertStringContainsString('DW', $svg);
    }
}
