<?php

namespace Tests\Feature;

use App\Enums\BundleStatus;
use App\Enums\ShareMode;
use App\Models\Bundle;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class UploadWorkflowTest extends TestCase
{
    private array $slugs = [];

    protected function tearDown(): void
    {
        foreach ($this->slugs as $slug) {
            Storage::disk('uploads')->deleteDirectory($slug);
        }

        parent::tearDown();
    }

    public function test_user_can_upload_metadata_file_and_complete_bundle(): void
    {
        Storage::fake('uploads');
        config(['approval.required_default' => false]);

        $user = User::factory()->create(['requires_approval' => false]);
        $slug = 'upload-'.Str::lower(Str::random(8));
        $this->slugs[] = $slug;

        $bundle = Bundle::create([
            'user_id' => $user->id,
            'slug' => $slug,
            'owner_token' => substr(sha1($slug.'owner'), 0, 15),
            'preview_token' => substr(sha1($slug.'preview'), 0, 15),
            'share_mode' => ShareMode::StaticLink,
            'completed' => false,
            'status' => BundleStatus::Draft,
            'expiry' => '86400',
            'fullsize' => 0,
            'max_downloads' => 0,
            'downloads' => 0,
        ]);

        $headers = [
            'X-Upload-Auth' => $bundle->owner_token,
            'X-Requested-With' => 'XMLHttpRequest',
        ];

        $this->actingAsUser($user)
            ->postJson("/upload/{$slug}", [
                'title' => 'Workflow bundle',
                'description' => 'Test description',
                'expiry' => '86400',
                'max_downloads' => 0,
                'password' => null,
                'auth' => $bundle->owner_token,
            ], $headers)
            ->assertOk()
            ->assertJsonPath('title', 'Workflow bundle');

        $uuid = (string) Str::uuid();

        $this->actingAsUser($user)
            ->postJson("/upload/{$slug}/file", [
                'uuid' => $uuid,
                'file' => UploadedFile::fake()->create('document.txt', 10, 'text/plain'),
            ], $headers)
            ->assertOk()
            ->assertJsonPath('original', 'document.txt');

        $this->actingAsUser($user)
            ->postJson("/upload/{$slug}/complete", [
                'auth' => $bundle->owner_token,
            ], $headers)
            ->assertOk()
            ->assertJsonPath('status', BundleStatus::Approved->value)
            ->assertJsonPath('completed', true);

        $bundle->refresh();
        $this->assertNotNull($bundle->preview_link);

        $this->get("/bundle/{$slug}/preview?auth={$bundle->preview_token}")
            ->assertOk()
            ->assertSee('Workflow bundle')
            ->assertSee('document.txt');
    }

    public function test_guest_can_upload_and_complete_bundle_when_ip_upload_allowed(): void
    {
        Storage::fake('uploads');
        config([
            'sso.enabled' => false,
            'sharing.upload_ip_limit' => null,
            'approval.required_default' => true,
        ]);

        $slug = 'guest-'.Str::lower(Str::random(8));
        $this->slugs[] = $slug;

        $bundle = Bundle::create([
            'user_id' => null,
            'slug' => $slug,
            'owner_token' => substr(sha1($slug.'owner'), 0, 15),
            'preview_token' => substr(sha1($slug.'preview'), 0, 15),
            'share_mode' => ShareMode::StaticLink,
            'completed' => false,
            'status' => BundleStatus::Draft,
            'expiry' => '86400',
            'fullsize' => 0,
            'max_downloads' => 0,
            'downloads' => 0,
        ]);

        $headers = [
            'X-Upload-Auth' => $bundle->owner_token,
            'X-Requested-With' => 'XMLHttpRequest',
        ];

        $this->postJson("/upload/{$slug}", [
            'title' => 'Guest bundle',
            'description' => 'Anonymous upload',
            'expiry' => '86400',
            'max_downloads' => 0,
            'password' => null,
            'auth' => $bundle->owner_token,
        ], $headers)
            ->assertOk()
            ->assertJsonPath('title', 'Guest bundle');

        $uuid = (string) Str::uuid();

        $this->postJson("/upload/{$slug}/file", [
            'uuid' => $uuid,
            'file' => UploadedFile::fake()->create('guest.txt', 10, 'text/plain'),
        ], $headers)
            ->assertOk()
            ->assertJsonPath('original', 'guest.txt');

        $this->postJson("/upload/{$slug}/complete", [
            'auth' => $bundle->owner_token,
        ], $headers)
            ->assertOk()
            ->assertJsonPath('status', BundleStatus::Approved->value)
            ->assertJsonPath('completed', true);

        $bundle->refresh();
        $this->assertNotNull($bundle->preview_link);
        $this->assertDatabaseMissing('approval_requests', ['bundle_id' => $bundle->id]);
    }

    public function test_owned_bundle_completion_requires_authentication(): void
    {
        config(['approval.required_default' => false]);

        $user = User::factory()->create();
        $slug = 'owned-'.Str::lower(Str::random(8));
        $this->slugs[] = $slug;

        $bundle = Bundle::create([
            'user_id' => $user->id,
            'slug' => $slug,
            'owner_token' => substr(sha1($slug.'owner'), 0, 15),
            'preview_token' => substr(sha1($slug.'preview'), 0, 15),
            'share_mode' => ShareMode::StaticLink,
            'completed' => false,
            'status' => BundleStatus::Draft,
            'expiry' => '86400',
            'fullsize' => 0,
            'max_downloads' => 0,
            'downloads' => 0,
        ]);

        $headers = [
            'X-Upload-Auth' => $bundle->owner_token,
            'X-Requested-With' => 'XMLHttpRequest',
        ];

        $this->postJson("/upload/{$slug}/complete", [
            'auth' => $bundle->owner_token,
        ], $headers)
            ->assertForbidden();
    }

    public function test_store_bundle_metadata_updates_draft_bundle(): void
    {
        $user = User::factory()->create();
        $slug = 'meta-'.Str::lower(Str::random(8));
        $this->slugs[] = $slug;

        $bundle = Bundle::create([
            'user_id' => $user->id,
            'slug' => $slug,
            'owner_token' => substr(sha1($slug.'owner'), 0, 15),
            'preview_token' => substr(sha1($slug.'preview'), 0, 15),
            'share_mode' => ShareMode::StaticLink,
            'completed' => false,
            'status' => BundleStatus::Draft,
            'expiry' => '86400',
            'fullsize' => 0,
            'max_downloads' => 0,
            'downloads' => 0,
        ]);

        $this->actingAsUser($user)
            ->postJson("/upload/{$slug}", [
                'title' => 'Updated title',
                'description' => 'Notes',
                'expiry' => '3600',
                'max_downloads' => 5,
                'password' => 'secret',
                'auth' => $bundle->owner_token,
            ], [
                'X-Upload-Auth' => $bundle->owner_token,
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->assertOk();

        $bundle->refresh();
        $this->assertSame('Updated title', $bundle->title);
        $this->assertSame('Notes', $bundle->description);
        $this->assertSame('3600', $bundle->expiry);
        $this->assertSame(5, $bundle->max_downloads);
        $this->assertSame('secret', $bundle->password);
    }
}
