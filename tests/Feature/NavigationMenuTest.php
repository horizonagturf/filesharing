<?php

namespace Tests\Feature;

use App\Enums\ApprovalRequestStatus;
use App\Models\ApprovalRequest;
use App\Models\Bundle;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

class NavigationMenuTest extends TestCase
{
    public function test_guest_sees_login_link_on_login_page(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee(__('app.do-login'))
            ->assertDontSee(__('app.nav-admin'))
            ->assertDontSee(__('approval.reviewer-nav'))
            ->assertDontSee(__('app.nav-account'));
    }

    public function test_standard_user_sees_home_account_and_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->get(route('homepage'))
            ->assertOk()
            ->assertSee(__('app.nav-home'))
            ->assertSee(__('app.nav-account'))
            ->assertSee(__('app.logout'))
            ->assertDontSee(__('app.nav-admin'))
            ->assertDontSee(__('approval.reviewer-nav'));
    }

    public function test_reviewer_sees_approval_queue_not_admin(): void
    {
        $reviewer = User::factory()->reviewer()->create();

        $this->actingAsUser($reviewer)
            ->get(route('homepage'))
            ->assertOk()
            ->assertSee(__('approval.reviewer-nav'))
            ->assertDontSee(__('app.nav-admin'));
    }

    public function test_admin_sees_approval_and_admin_links(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAsUser($admin)
            ->get(route('homepage'))
            ->assertOk()
            ->assertSee(__('approval.reviewer-nav'))
            ->assertSee(__('app.nav-admin'));
    }

    public function test_reviewer_sees_pending_approval_badge_count(): void
    {
        $reviewer = User::factory()->reviewer()->create();
        $uploader = User::factory()->create();
        $bundle = $this->createBundle($uploader);

        ApprovalRequest::create([
            'bundle_id' => $bundle->id,
            'requested_by' => $uploader->id,
            'status' => ApprovalRequestStatus::Pending,
        ]);

        $this->actingAsUser($reviewer)
            ->get(route('homepage'))
            ->assertOk()
            ->assertSee('1', false);
    }

    public function test_guest_sees_login_on_homepage_when_upload_allowed(): void
    {
        config(['sso.enabled' => false]);

        $this->get(route('homepage'))
            ->assertOk()
            ->assertSee(__('app.do-login'))
            ->assertDontSee(__('app.nav-account'));
    }

    public function test_account_page_shows_roles_groups_and_approval_fields(): void
    {
        config(['approval.required_default' => true]);

        $user = User::factory()->create([
            'username' => 'detailuser',
            'email' => 'detail@example.com',
            'requires_approval' => false,
        ]);
        $group = Group::create([
            'name' => 'Test Group',
            'slug' => 'test-group-nav',
            'requires_approval' => true,
        ]);
        $user->groups()->attach($group);

        $this->actingAsUser($user)
            ->get(route('account'))
            ->assertOk()
            ->assertSee('detailuser')
            ->assertSee('detail@example.com')
            ->assertSee('user')
            ->assertSee('Test Group')
            ->assertSee(__('app.account-approval-effective'))
            ->assertSee(__('app.no'))
            ->assertSee(__('app.account-view-bundles'));
    }

    public function test_account_page_shows_profile_for_authenticated_user(): void
    {
        $user = User::factory()->create([
            'username' => 'navuser',
            'email' => 'nav@example.com',
        ]);

        $this->actingAsUser($user)
            ->get(route('account'))
            ->assertOk()
            ->assertSee('navuser')
            ->assertSee('nav@example.com')
            ->assertSee(__('app.account-view-bundles'));
    }

    public function test_guest_is_redirected_from_account_page(): void
    {
        $this->get(route('account'))
            ->assertRedirect(route('login'));
    }

    private function createBundle(User $user): Bundle
    {
        $slug = 'nav-'.Str::lower(Str::random(8));

        return Bundle::create([
            'user_id' => $user->id,
            'slug' => $slug,
            'title' => 'Nav test bundle',
            'owner_token' => substr(sha1($slug.'owner'), 0, 15),
            'preview_token' => substr(sha1($slug.'preview'), 0, 15),
            'completed' => true,
            'status' => 'pending_approval',
            'expiry' => '86400',
            'fullsize' => 0,
            'max_downloads' => 0,
            'downloads' => 0,
        ]);
    }
}
