<?php

namespace Tests\Unit;

use App\Services\BrandingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandingSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_persist_and_clear_cache(): void
    {
        $branding = app(BrandingSettings::class);

        $branding->set(BrandingSettings::KEY_APP_NAME, 'Acme Files');
        $branding->set(BrandingSettings::KEY_PRIMARY_COLOR, '#ff0000');

        $this->assertSame('Acme Files', $branding->appName());
        $this->assertSame('255 0 0', $branding->cssVariables()['--color-primary']);

        $branding->set(BrandingSettings::KEY_APP_NAME, null);

        $this->assertSame(config('app.name'), $branding->appName());
    }

    public function test_logo_url_uses_public_storage_path(): void
    {
        $branding = app(BrandingSettings::class);

        $branding->set(BrandingSettings::KEY_LOGO_PATH, 'branding/logo.png');

        $this->assertStringContainsString('storage/branding/logo.png', $branding->logoUrl());
    }

    public function test_logo_url_falls_back_to_default_when_not_set(): void
    {
        $branding = app(BrandingSettings::class);

        $this->assertStringContainsString('images/logo.svg', $branding->logoUrl());
    }

    public function test_show_credit_footer_uses_env_default_when_not_overridden(): void
    {
        config(['branding.show_credit' => true]);

        $branding = app(BrandingSettings::class);

        $this->assertTrue($branding->showCreditFooter());

        config(['branding.show_credit' => false]);

        $this->assertFalse($branding->showCreditFooter());
    }

    public function test_show_credit_footer_respects_database_override(): void
    {
        config(['branding.show_credit' => false]);

        $branding = app(BrandingSettings::class);

        $branding->set(BrandingSettings::KEY_SHOW_CREDIT, '0');
        $this->assertFalse($branding->showCreditFooter());

        $branding->set(BrandingSettings::KEY_SHOW_CREDIT, '1');
        $this->assertTrue($branding->showCreditFooter());
    }
}
