<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\BrandingSettings;
use Tests\TestCase;

class BrandingThemeTest extends TestCase
{
    public function test_layout_renders_logo_footer_and_css_variables(): void
    {
        $branding = app(BrandingSettings::class);
        $branding->set(BrandingSettings::KEY_APP_NAME, 'Branded Org');
        $branding->set(BrandingSettings::KEY_LOGO_PATH, 'branding/logo.png');
        $branding->set(BrandingSettings::KEY_PRIMARY_COLOR, '#112233');
        $branding->set(BrandingSettings::KEY_ACCENT_COLOR, '#445566');
        $branding->set(BrandingSettings::KEY_FOOTER_TEXT, 'Confidential');
        $branding->set(BrandingSettings::KEY_TOS_URL, 'https://example.com/terms');
        $branding->set(BrandingSettings::KEY_PRIVACY_URL, 'https://example.com/privacy');

        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('Branded Org');
        $response->assertSee('storage/branding/logo.png', false);
        $response->assertSee('--color-primary: 17 34 51', false);
        $response->assertSee('--color-primary-light: 68 85 102', false);
        $response->assertSee('content="#112233"', false);
        $response->assertSee('Confidential');
        $response->assertSee('https://example.com/terms', false);
        $response->assertSee('https://example.com/privacy', false);
    }

    public function test_nav_shows_admin_link_for_admin_user(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAsUser($admin)
            ->get(route('homepage'))
            ->assertOk()
            ->assertSee(__('app.nav-admin'))
            ->assertSee('/admin', false);
    }

    public function test_nav_hides_admin_panel_label_for_reviewer_without_admin(): void
    {
        $reviewer = User::factory()->reviewer()->create();

        $this->actingAsUser($reviewer)
            ->get(route('homepage'))
            ->assertOk()
            ->assertDontSee(__('app.nav-admin'));
    }

    public function test_footer_shows_project_credit_by_default(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('axeloz');
    }

    public function test_footer_hides_project_credit_when_disabled_in_database(): void
    {
        app(BrandingSettings::class)->set(BrandingSettings::KEY_SHOW_CREDIT, '0');

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('axeloz');
    }

    public function test_footer_hides_project_credit_when_disabled_in_env(): void
    {
        config(['branding.show_credit' => false]);

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('axeloz');
    }
}
