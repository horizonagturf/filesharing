<?php

namespace Tests\Feature;

use App\Enums\ShareMode;
use App\Models\Bundle;
use App\Models\File;
use App\Models\Group;
use App\Models\User;
use App\Services\SharingSettings;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShareModeTest extends TestCase
{
    public function test_new_bundle_inherits_org_default_share_mode(): void
    {
        app(SharingSettings::class)->setDefaultShareMode(ShareMode::Invitation);

        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->postJson('/new', [], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $this->assertDatabaseHas('bundles', [
            'user_id' => $user->id,
            'share_mode' => ShareMode::Invitation->value,
        ]);
    }

    public function test_new_bundle_falls_back_to_invitation_when_org_default_is_static_but_user_lacks_permission(): void
    {
        app(SharingSettings::class)->setDefaultShareMode(ShareMode::StaticLink);

        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->postJson('/new', [], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $this->assertDatabaseHas('bundles', [
            'user_id' => $user->id,
            'share_mode' => ShareMode::Invitation->value,
        ]);
    }

    public function test_store_bundle_without_share_mode_clamps_existing_static_mode_for_unauthorized_user(): void
    {
        app(SharingSettings::class)->setDefaultShareMode(ShareMode::StaticLink);

        $user = User::factory()->create();
        $bundle = $this->createBundle($user, shareMode: ShareMode::StaticLink);

        $this->actingAsUser($user)
            ->postJson("/upload/{$bundle->slug}", [
                'title' => 'Test',
                'expiry' => '86400',
                'max_downloads' => 0,
                'auth' => $bundle->owner_token,
            ], $this->uploadHeaders($bundle))
            ->assertOk()
            ->assertJsonPath('share_mode', ShareMode::Invitation->value);

        $this->assertDatabaseHas('bundles', [
            'id' => $bundle->id,
            'share_mode' => ShareMode::Invitation->value,
        ]);
    }

    public function test_user_without_static_link_group_cannot_select_static_mode(): void
    {
        $user = User::factory()->create();
        $bundle = $this->createBundle($user);

        $this->actingAsUser($user)
            ->postJson("/upload/{$bundle->slug}", [
                'title' => 'Test',
                'expiry' => '86400',
                'max_downloads' => 0,
                'share_mode' => ShareMode::StaticLink->value,
                'auth' => $bundle->owner_token,
            ], $this->uploadHeaders($bundle))
            ->assertStatus(422)
            ->assertJsonPath('message', __('sharing.static-link-not-allowed'));
    }

    public function test_user_in_allowed_group_can_use_static_link_mode(): void
    {
        $user = User::factory()->create();
        $group = Group::create([
            'name' => 'Trusted',
            'slug' => 'trusted',
            'requires_approval' => false,
            'allow_static_links' => true,
        ]);
        $user->groups()->attach($group);

        $bundle = $this->createBundle($user);

        $this->actingAsUser($user)
            ->postJson("/upload/{$bundle->slug}", [
                'title' => 'Test',
                'expiry' => '86400',
                'max_downloads' => 0,
                'share_mode' => ShareMode::StaticLink->value,
                'auth' => $bundle->owner_token,
            ], $this->uploadHeaders($bundle))
            ->assertOk()
            ->assertJsonPath('share_mode', ShareMode::StaticLink->value);
    }

    public function test_static_link_bundle_grants_access_with_preview_token(): void
    {
        $user = User::factory()->create(['requires_approval' => false]);
        $group = Group::create([
            'name' => 'Trusted',
            'slug' => 'trusted-static',
            'requires_approval' => false,
            'allow_static_links' => true,
        ]);
        $user->groups()->attach($group);

        $bundle = $this->createBundle($user, shareMode: ShareMode::StaticLink);

        $this->actingAsUser($user)
            ->postJson("/upload/{$bundle->slug}/complete", [
                'auth' => $bundle->owner_token,
            ], $this->uploadHeaders($bundle))
            ->assertOk()
            ->assertJsonPath('status', 'approved')
            ->assertJsonPath('preview_link', fn ($link) => str_contains($link, 'auth='.$bundle->preview_token));

        $this->get("/bundle/{$bundle->slug}/preview?auth={$bundle->preview_token}")
            ->assertOk();
    }

    public function test_invitation_bundle_blocks_unverified_access(): void
    {
        $user = User::factory()->create(['requires_approval' => false]);
        $bundle = $this->createBundle($user, shareMode: ShareMode::Invitation);
        $bundle->update(['status' => 'approved', 'completed' => true]);

        $this->get("/bundle/{$bundle->slug}/preview?auth={$bundle->preview_token}")
            ->assertForbidden();
    }

    public function test_admin_can_set_org_default_share_mode(): void
    {
        $sharing = app(SharingSettings::class);

        $sharing->setDefaultShareMode(ShareMode::StaticLink);
        $this->assertSame(ShareMode::StaticLink, $sharing->defaultShareMode());

        $sharing->setDefaultShareMode(ShareMode::Invitation);
        $this->assertSame(ShareMode::Invitation, $sharing->defaultShareMode());
    }

    public function test_admin_can_set_blocked_extensions_override(): void
    {
        $sharing = app(SharingSettings::class);

        $sharing->setBlockedExtensions(['txt', 'pdf']);
        $this->assertTrue($sharing->hasBlockedExtensionsOverride());
        $this->assertSame(['txt', 'pdf'], $sharing->blockedExtensions());

        $sharing->setBlockedExtensions(null);
        $this->assertFalse($sharing->hasBlockedExtensionsOverride());
    }

    private function createBundle(User $user, ShareMode $shareMode = ShareMode::Invitation): Bundle
    {
        $slug = 'bundle-'.Str::lower(Str::random(8));

        $bundle = Bundle::create([
            'user_id' => $user->id,
            'slug' => $slug,
            'title' => 'Test bundle',
            'owner_token' => substr(sha1($slug.'owner'), 0, 15),
            'preview_token' => substr(sha1($slug.'preview'), 0, 15),
            'share_mode' => $shareMode,
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
