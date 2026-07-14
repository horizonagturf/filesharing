<?php

namespace Tests\Feature;

use App\Enums\BundleStatus;
use App\Enums\ShareMode;
use App\Models\Bundle;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

class UploadOwnerAccessTest extends TestCase
{
    public function test_non_owner_cannot_open_upload_page(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $bundle = $this->createDraftBundle($owner);

        $this->actingAsUser($other)
            ->get("/upload/{$bundle->slug}")
            ->assertForbidden();
    }

    public function test_owner_can_open_upload_page_without_token(): void
    {
        $owner = User::factory()->create();
        $bundle = $this->createDraftBundle($owner);

        $this->actingAsUser($owner)
            ->get("/upload/{$bundle->slug}")
            ->assertOk()
            ->assertSee($bundle->owner_token, false);
    }

    public function test_owner_token_allows_upload_page_for_anonymous_capability_holder(): void
    {
        config(['sso.enabled' => false]);

        $bundle = Bundle::create([
            'user_id' => null,
            'slug' => 'anon-'.Str::lower(Str::random(8)),
            'title' => 'Anon draft',
            'owner_token' => substr(sha1('owner-anon'), 0, 15),
            'preview_token' => substr(sha1('preview-anon'), 0, 15),
            'share_mode' => ShareMode::StaticLink,
            'completed' => false,
            'status' => BundleStatus::Draft,
            'expiry' => '86400',
            'fullsize' => 0,
            'max_downloads' => 0,
            'downloads' => 0,
        ]);

        $this->get("/upload/{$bundle->slug}")
            ->assertForbidden();

        $this->get("/upload/{$bundle->slug}?auth={$bundle->owner_token}")
            ->assertOk()
            ->assertSee($bundle->owner_token, false);
    }

    public function test_new_bundle_redirect_includes_owner_token(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAsUser($user)
            ->postJson('/new', [], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $bundle = Bundle::query()->where('user_id', $user->id)->latest('id')->first();
        $this->assertNotNull($bundle);
        $this->assertStringContainsString('auth='.$bundle->owner_token, $response->json('redirect'));
        $this->assertSame($bundle->owner_token, $response->json('bundle.owner_token'));
    }

    private function createDraftBundle(User $owner): Bundle
    {
        $slug = 'owner-access-'.Str::lower(Str::random(8));

        return Bundle::create([
            'user_id' => $owner->id,
            'slug' => $slug,
            'title' => 'Owner access',
            'owner_token' => substr(sha1('owner-'.$slug), 0, 15),
            'preview_token' => substr(sha1('preview-'.$slug), 0, 15),
            'share_mode' => ShareMode::StaticLink,
            'completed' => false,
            'status' => BundleStatus::Draft,
            'expiry' => '86400',
            'fullsize' => 0,
            'max_downloads' => 0,
            'downloads' => 0,
        ]);
    }
}
