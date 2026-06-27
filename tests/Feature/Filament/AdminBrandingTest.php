<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\ManageBranding;
use App\Models\Setting;
use App\Models\User;
use App\Services\BrandingSettings;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AdminBrandingTest extends TestCase
{
    public function test_admin_can_save_branding_settings(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(ManageBranding::class)
            ->fillForm([
                'app_name' => 'Org File Send',
                'primary_color' => '#112233',
                'accent_color' => '#445566',
                'footer_text' => 'Internal use only',
                'tos_url' => 'https://example.com/terms',
                'privacy_url' => 'https://example.com/privacy',
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $branding = app(BrandingSettings::class);
        $this->assertSame('Org File Send', $branding->appName());
        $this->assertSame('Internal use only', $branding->get(BrandingSettings::KEY_FOOTER_TEXT));
        $this->assertSame('https://example.com/terms', $branding->get(BrandingSettings::KEY_TOS_URL));
        $this->assertSame('https://example.com/privacy', $branding->get(BrandingSettings::KEY_PRIVACY_URL));
        $this->assertSame('17 34 51', $branding->cssVariables()['--color-primary']);
        $this->assertSame('68 85 102', $branding->cssVariables()['--color-primary-light']);
    }

    public function test_admin_can_hide_project_credit(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(ManageBranding::class)
            ->fillForm([
                'primary_color' => '#112233',
                'accent_color' => '#445566',
                'show_credit' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $branding = app(BrandingSettings::class);

        $this->assertFalse($branding->showCreditFooter());
        $this->assertSame('0', $branding->get(BrandingSettings::KEY_SHOW_CREDIT));
    }

    public function test_admin_clears_credit_override_when_matching_env_default(): void
    {
        config(['branding.show_credit' => true]);

        $admin = User::factory()->admin()->create();
        $branding = app(BrandingSettings::class);
        $branding->set(BrandingSettings::KEY_SHOW_CREDIT, '0');

        Livewire::actingAs($admin)
            ->test(ManageBranding::class)
            ->fillForm([
                'primary_color' => '#112233',
                'accent_color' => '#445566',
                'show_credit' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $this->assertTrue($branding->showCreditFooter());
        $this->assertFalse(
            Setting::query()->where('key', BrandingSettings::KEY_SHOW_CREDIT)->exists()
        );
    }

    public function test_saving_other_fields_does_not_clear_existing_logo(): void
    {
        $admin = User::factory()->admin()->create();
        $branding = app(BrandingSettings::class);
        $branding->set(BrandingSettings::KEY_LOGO_PATH, 'branding/existing-logo.png');

        Livewire::actingAs($admin)
            ->test(ManageBranding::class)
            ->fillForm(['app_name' => 'Updated Name'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $this->assertSame('branding/existing-logo.png', $branding->get(BrandingSettings::KEY_LOGO_PATH));
        $this->assertSame('Updated Name', $branding->appName());
    }

    public function test_admin_can_clear_existing_logo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('branding/existing-logo.png', 'fake-image');

        $admin = User::factory()->admin()->create();
        $branding = app(BrandingSettings::class);
        $branding->set(BrandingSettings::KEY_LOGO_PATH, 'branding/existing-logo.png');

        Livewire::actingAs($admin)
            ->test(ManageBranding::class)
            ->fillForm(['logo_path' => null])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $this->assertNull($branding->get(BrandingSettings::KEY_LOGO_PATH));
    }
}
