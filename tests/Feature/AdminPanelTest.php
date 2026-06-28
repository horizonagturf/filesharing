<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\User;
use App\Services\ApprovalPolicy;
use App\Services\BrandingSettings;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    public function test_admin_can_access_panel(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAsUser($admin)
            ->get('/admin')
            ->assertOk();
    }

    public function test_non_admin_cannot_access_panel(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin')
            ->assertRedirect(route('login'));
    }

    public function test_admin_can_open_user_edit_form(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['requires_approval' => null]);

        $this->actingAsUser($admin)
            ->get("/admin/users/{$user->id}/edit")
            ->assertOk()
            ->assertSee($user->username);
    }

    public function test_user_approval_override_is_persisted(): void
    {
        $user = User::factory()->create(['requires_approval' => null]);

        $user->update(['requires_approval' => true]);

        $this->assertTrue($user->fresh()->requires_approval);
    }

    public function test_group_approval_flag_affects_policy(): void
    {
        $user = User::factory()->create(['requires_approval' => null]);
        $group = Group::create([
            'name' => 'Needs approval',
            'slug' => 'needs-approval',
            'requires_approval' => false,
        ]);

        $policy = app(ApprovalPolicy::class);
        $this->assertFalse($policy->requiresApproval($user));

        $group->update(['requires_approval' => true]);
        $group->users()->attach($user);
        $user->load('groups');

        $this->assertTrue($policy->requiresApproval($user));
    }

    public function test_branding_settings_render_in_layout(): void
    {
        $branding = app(BrandingSettings::class);
        $branding->set(BrandingSettings::KEY_APP_NAME, 'Branded Send');
        $branding->set(BrandingSettings::KEY_PRIMARY_COLOR, '#112233');

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Branded Send')
            ->assertSee('--color-primary: 17 34 51');
    }

    public function test_reviewer_pool_page_lists_reviewers(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->reviewer()->create(['username' => 'reviewer1']);

        $this->actingAsUser($admin)
            ->get('/admin/reviewer-pool')
            ->assertOk()
            ->assertSee('reviewer1');
    }

    public function test_reviewer_without_admin_cannot_access_panel(): void
    {
        $reviewer = User::factory()->reviewer()->create();

        $this->actingAsUser($reviewer)
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_admin_pages_are_reachable(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAsUser($admin)
            ->get('/admin/bundles')
            ->assertOk();

        $this->actingAsUser($admin)
            ->get('/admin/groups')
            ->assertOk();

        $this->actingAsUser($admin)
            ->get('/admin/manage-branding')
            ->assertOk();
    }

    public function test_admin_panel_shows_back_to_app_link(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAsUser($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee(__('app.nav-back-to-app'))
            ->assertSee(__('app.nav-app-home'));
    }
}
