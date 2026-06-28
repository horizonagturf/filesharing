<?php

namespace Tests\Feature;

use App\Enums\ShareMode;
use App\Models\Bundle;
use App\Models\File;
use App\Models\Group;
use App\Models\User;
use App\Services\OtpPolicy;
use App\Services\SharingSettings;
use Illuminate\Support\Str;
use Tests\TestCase;

class OtpPolicyTest extends TestCase
{
    public function test_new_bundle_inherits_org_default_require_otp(): void
    {
        config(['sharing.invitation_require_otp' => false]);

        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->postJson('/new', [], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $this->assertDatabaseHas('bundles', [
            'user_id' => $user->id,
            'require_otp' => false,
        ]);
    }

    public function test_admin_can_set_org_default_require_otp(): void
    {
        $sharing = app(SharingSettings::class);

        $sharing->setInvitationRequireOtp(false);
        $this->assertFalse($sharing->invitationRequireOtp());

        $sharing->setInvitationRequireOtp(true);
        $this->assertTrue($sharing->invitationRequireOtp());
    }

    public function test_user_without_group_permission_cannot_disable_otp_when_org_default_requires_it(): void
    {
        config(['sharing.invitation_require_otp' => true]);

        $user = User::factory()->create();
        $bundle = $this->createBundle($user);

        $this->actingAsUser($user)
            ->postJson("/upload/{$bundle->slug}", [
                'title' => 'Test',
                'expiry' => '86400',
                'max_downloads' => 0,
                'require_otp' => false,
                'auth' => $bundle->owner_token,
            ], $this->uploadHeaders($bundle))
            ->assertStatus(422)
            ->assertJsonPath('message', __('sharing.otp-skip-not-allowed'));
    }

    public function test_user_in_allowed_group_can_disable_otp(): void
    {
        config(['sharing.invitation_require_otp' => true]);

        $user = User::factory()->create();
        $group = Group::create([
            'name' => 'No OTP',
            'slug' => 'no-otp',
            'requires_approval' => false,
            'allow_static_links' => false,
            'allow_invitation_without_otp' => true,
        ]);
        $user->groups()->attach($group);

        $bundle = $this->createBundle($user);

        $this->actingAsUser($user)
            ->postJson("/upload/{$bundle->slug}", [
                'title' => 'Test',
                'expiry' => '86400',
                'max_downloads' => 0,
                'require_otp' => false,
                'auth' => $bundle->owner_token,
            ], $this->uploadHeaders($bundle))
            ->assertOk()
            ->assertJsonPath('require_otp', false);

        $this->assertDatabaseHas('bundles', [
            'id' => $bundle->id,
            'require_otp' => false,
        ]);
    }

    public function test_static_link_mode_ignores_require_otp_flag(): void
    {
        $user = User::factory()->create();
        $group = Group::create([
            'name' => 'Trusted',
            'slug' => 'trusted-otp',
            'requires_approval' => false,
            'allow_static_links' => true,
            'allow_invitation_without_otp' => true,
        ]);
        $user->groups()->attach($group);

        $bundle = $this->createBundle($user);

        $this->actingAsUser($user)
            ->postJson("/upload/{$bundle->slug}", [
                'title' => 'Test',
                'expiry' => '86400',
                'max_downloads' => 0,
                'share_mode' => ShareMode::StaticLink->value,
                'require_otp' => false,
                'auth' => $bundle->owner_token,
            ], $this->uploadHeaders($bundle))
            ->assertOk()
            ->assertJsonPath('share_mode', ShareMode::StaticLink->value)
            ->assertJsonPath('require_otp', true);
    }

    public function test_effective_require_otp_clamps_unauthorized_skip(): void
    {
        config(['sharing.invitation_require_otp' => true]);

        $policy = app(OtpPolicy::class);
        $user = User::factory()->create();

        $this->assertTrue($policy->effectiveRequireOtp($user, false, ShareMode::Invitation));
        $this->assertFalse($policy->canChooseOtpSetting($user));
    }

    public function test_can_choose_otp_when_org_default_is_false(): void
    {
        config(['sharing.invitation_require_otp' => false]);

        $policy = app(OtpPolicy::class);
        $user = User::factory()->create();

        $this->assertTrue($policy->canChooseOtpSetting($user));
    }

    public function test_upload_settings_shows_otp_instructions_when_user_can_choose(): void
    {
        config(['sharing.invitation_require_otp' => true]);

        $user = User::factory()->create();
        $group = Group::create([
            'name' => 'No OTP',
            'slug' => 'no-otp-ui',
            'requires_approval' => false,
            'allow_static_links' => false,
            'allow_invitation_without_otp' => true,
        ]);
        $user->groups()->attach($group);

        $bundle = $this->createBundle($user);

        $this->actingAsUser($user)
            ->get("/upload/{$bundle->slug}")
            ->assertOk()
            ->assertSee(__('sharing.require-otp-enabled-info'), false)
            ->assertSee(__('sharing.require-otp-disabled-warning'), false);
    }

    private function createBundle(User $user): Bundle
    {
        $slug = 'bundle-'.Str::lower(Str::random(8));

        $bundle = Bundle::create([
            'user_id' => $user->id,
            'slug' => $slug,
            'title' => 'Test bundle',
            'owner_token' => substr(sha1($slug.'owner'), 0, 15),
            'preview_token' => substr(sha1($slug.'preview'), 0, 15),
            'share_mode' => ShareMode::Invitation,
            'require_otp' => true,
            'completed' => false,
            'status' => 'draft',
            'expiry' => '86400',
            'fullsize' => 100,
            'max_downloads' => 0,
            'downloads' => 0,
        ]);

        File::create([
            'uuid' => (string) Str::uuid(),
            'bundle_id' => $bundle->id,
            'original' => 'test.txt',
            'filesize' => 100,
            'fullpath' => "{$slug}/test.txt",
            'filename' => 'test.txt',
            'status' => true,
        ]);

        return $bundle;
    }

    private function uploadHeaders(Bundle $bundle): array
    {
        return [
            'X-Upload-Auth' => $bundle->owner_token,
            'X-Requested-With' => 'XMLHttpRequest',
        ];
    }
}
