<?php

namespace Tests\Feature;

use App\Enums\BundleStatus;
use App\Enums\ShareMode;
use App\Mail\BundleInvitationMail;
use App\Models\Bundle;
use App\Models\File;
use App\Models\Group;
use App\Models\User;
use App\Services\SharingSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
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
        $group = Group::create([
            'name' => 'Trusted upload',
            'slug' => 'trusted-upload',
            'requires_approval' => false,
            'allow_static_links' => true,
        ]);
        $user->groups()->attach($group);
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
                'share_mode' => ShareMode::StaticLink->value,
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
        Mail::fake();
        config([
            'sso.enabled' => false,
            'sharing.upload_ip_limit' => null,
            'approval.required_default' => true,
            'sharing.default_share_mode' => 'invitation',
        ]);

        $slug = 'guest-'.Str::lower(Str::random(8));
        $this->slugs[] = $slug;

        $bundle = Bundle::create([
            'user_id' => null,
            'slug' => $slug,
            'owner_token' => substr(sha1($slug.'owner'), 0, 15),
            'preview_token' => substr(sha1($slug.'preview'), 0, 15),
            'share_mode' => ShareMode::Invitation,
            'require_otp' => true,
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
            'recipients' => ['guest@example.com'],
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
            ->assertJsonPath('status', BundleStatus::Sent->value)
            ->assertJsonPath('completed', true);

        $bundle->refresh();
        $this->assertNull($bundle->preview_link);
        $this->assertDatabaseMissing('approval_requests', ['bundle_id' => $bundle->id]);
        Mail::assertQueued(BundleInvitationMail::class);
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

    public function test_blocked_file_extension_returns_422(): void
    {
        Storage::fake('uploads');
        config(['sharing.upload_blocked_extensions' => 'exe,bat,ps1']);

        $user = User::factory()->create(['requires_approval' => false]);
        $slug = 'blocked-'.Str::lower(Str::random(8));
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
            ->postJson("/upload/{$slug}/file", [
                'uuid' => (string) Str::uuid(),
                'file' => UploadedFile::fake()->create('malware.exe', 10, 'application/octet-stream'),
            ], $headers)
            ->assertUnprocessable()
            ->assertJsonPath('message', __('app.file-type-blocked'));
    }

    public function test_allowed_file_extension_uploads_successfully_with_blocklist_enabled(): void
    {
        Storage::fake('uploads');
        config(['sharing.upload_blocked_extensions' => 'exe,bat,ps1']);

        $user = User::factory()->create(['requires_approval' => false]);
        $slug = 'allowed-'.Str::lower(Str::random(8));
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
            ->postJson("/upload/{$slug}/file", [
                'uuid' => (string) Str::uuid(),
                'file' => UploadedFile::fake()->create('document.txt', 10, 'text/plain'),
            ], $headers)
            ->assertOk()
            ->assertJsonPath('original', 'document.txt');
    }

    public function test_db_override_can_disable_blocklist(): void
    {
        Storage::fake('uploads');
        config(['sharing.upload_blocked_extensions' => 'exe,bat,ps1']);

        app(SharingSettings::class)->setBlockedExtensions([]);

        $user = User::factory()->create(['requires_approval' => false]);
        $slug = 'override-off-'.Str::lower(Str::random(8));
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
            ->postJson("/upload/{$slug}/file", [
                'uuid' => (string) Str::uuid(),
                'file' => UploadedFile::fake()->create('malware.exe', 10, 'application/octet-stream'),
            ], $headers)
            ->assertOk()
            ->assertJsonPath('original', 'malware.exe');
    }

    public function test_db_override_can_add_custom_blocked_extension(): void
    {
        Storage::fake('uploads');
        config(['sharing.upload_blocked_extensions' => 'exe']);

        app(SharingSettings::class)->setBlockedExtensions(['txt']);

        $user = User::factory()->create(['requires_approval' => false]);
        $slug = 'override-txt-'.Str::lower(Str::random(8));
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
            ->postJson("/upload/{$slug}/file", [
                'uuid' => (string) Str::uuid(),
                'file' => UploadedFile::fake()->create('document.txt', 10, 'text/plain'),
            ], $headers)
            ->assertUnprocessable()
            ->assertJsonPath('message', __('app.file-type-blocked'));
    }

    public function test_uploading_image_generates_thumbnail_and_owner_can_fetch_it(): void
    {
        Storage::fake('uploads');
        config(['approval.required_default' => false]);

        $user = User::factory()->create(['requires_approval' => false]);
        $slug = 'imgup-'.Str::lower(Str::random(8));
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

        $uuid = (string) Str::uuid();

        $response = $this->actingAsUser($user)
            ->postJson("/upload/{$slug}/file", [
                'uuid' => $uuid,
                'file' => UploadedFile::fake()->image('photo.png', 300, 200),
            ], $headers)
            ->assertOk()
            ->assertJsonPath('original', 'photo.png')
            ->assertJsonPath('is_image', true);

        $response->assertJsonStructure(['thumbnail_url']);
        $this->assertNotEmpty($response->json('thumbnail_url'));

        $file = File::where('uuid', $uuid)->firstOrFail();
        $this->assertNotNull($file->thumbnail_path);
        Storage::disk('uploads')->assertExists($file->thumbnail_path);

        $this->actingAsUser($user)
            ->get("/upload/{$slug}/file/{$uuid}/thumbnail?auth={$bundle->owner_token}")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');

        $this->actingAsUser($user)
            ->deleteJson("/upload/{$slug}/file", [
                'uuid' => $uuid,
                'auth' => $bundle->owner_token,
            ], $headers)
            ->assertOk();

        Storage::disk('uploads')->assertMissing($file->thumbnail_path);
        $this->assertDatabaseMissing('files', ['uuid' => $uuid]);
    }

    public function test_guest_preview_response_omits_file_fullpath(): void
    {
        Storage::fake('uploads');
        config(['approval.required_default' => false, 'sso.enabled' => true]);

        $user = User::factory()->create(['requires_approval' => false]);
        $slug = 'guestpath-'.Str::lower(Str::random(8));
        $this->slugs[] = $slug;

        $bundle = Bundle::create([
            'user_id' => $user->id,
            'slug' => $slug,
            'title' => 'Guest path check',
            'owner_token' => substr(sha1($slug.'owner'), 0, 15),
            'preview_token' => substr(sha1($slug.'preview'), 0, 15),
            'share_mode' => ShareMode::StaticLink,
            'completed' => true,
            'status' => BundleStatus::Approved,
            'expiry' => '86400',
            'expires_at' => now()->addDay(),
            'fullsize' => 10,
            'max_downloads' => 0,
            'downloads' => 0,
        ]);

        File::create([
            'uuid' => (string) Str::uuid(),
            'bundle_id' => $bundle->id,
            'original' => 'secret.txt',
            'filesize' => 10,
            'fullpath' => "{$slug}/secret.txt",
            'filename' => 'secret',
            'status' => true,
        ]);

        $this->get("/bundle/{$slug}/preview?auth={$bundle->preview_token}")
            ->assertOk()
            ->assertDontSee("{$slug}/secret.txt", false);

        $viewer = User::factory()->create();

        $this->actingAsUser($viewer)
            ->get("/bundle/{$slug}/preview?auth={$bundle->preview_token}")
            ->assertOk()
            ->assertDontSee("{$slug}/secret.txt", false);
    }

    public function test_guest_preview_omits_owner_secrets_when_sso_disabled(): void
    {
        Storage::fake('uploads');
        config(['approval.required_default' => false, 'sso.enabled' => false]);

        $user = User::factory()->create(['requires_approval' => false]);
        $slug = 'guestopen-'.Str::lower(Str::random(8));
        $this->slugs[] = $slug;

        $bundle = Bundle::create([
            'user_id' => $user->id,
            'slug' => $slug,
            'title' => 'Open upload guest check',
            'password' => 'bundle-secret',
            'owner_token' => substr(sha1($slug.'owner'), 0, 15),
            'preview_token' => substr(sha1($slug.'preview'), 0, 15),
            'share_mode' => ShareMode::StaticLink,
            'completed' => true,
            'status' => BundleStatus::Approved,
            'expiry' => '86400',
            'expires_at' => now()->addDay(),
            'fullsize' => 10,
            'max_downloads' => 0,
            'downloads' => 0,
        ]);

        File::create([
            'uuid' => (string) Str::uuid(),
            'bundle_id' => $bundle->id,
            'original' => 'secret.txt',
            'filesize' => 10,
            'fullpath' => "{$slug}/secret.txt",
            'filename' => 'secret',
            'hash' => 'deadbeef',
            'status' => true,
        ]);

        $response = $this->get("/bundle/{$slug}/preview?auth={$bundle->preview_token}")
            ->assertOk();

        $payload = $this->decodeBundlePayload($response->getContent());

        $this->assertArrayNotHasKey('owner_token', $payload);
        $this->assertArrayNotHasKey('preview_token', $payload);
        $this->assertArrayNotHasKey('password', $payload);
        $this->assertArrayNotHasKey('deletion_link', $payload);
        $this->assertCount(1, $payload['files']);
        $this->assertArrayNotHasKey('fullpath', $payload['files'][0]);
        $this->assertArrayNotHasKey('hash', $payload['files'][0]);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeBundlePayload(string $html): array
    {
        $this->assertSame(1, preg_match("/window\.__bundle = JSON\.parse\('(.*)'\);/s", $html, $matches));

        $json = json_decode('"'.$matches[1].'"');
        $this->assertIsString($json);

        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);

        return $decoded;
    }
}
