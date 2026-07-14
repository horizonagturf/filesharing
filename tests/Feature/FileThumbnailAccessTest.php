<?php

namespace Tests\Feature;

use App\Enums\ApprovalRequestStatus;
use App\Enums\BundleStatus;
use App\Enums\ShareMode;
use App\Models\ApprovalRequest;
use App\Models\Bundle;
use App\Models\BundleRecipient;
use App\Models\File;
use App\Models\User;
use App\Services\ImageThumbnailService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class FileThumbnailAccessTest extends TestCase
{
    private array $slugs = [];

    protected function tearDown(): void
    {
        foreach ($this->slugs as $slug) {
            Storage::disk('uploads')->deleteDirectory($slug);
        }

        parent::tearDown();
    }

    public function test_static_link_guest_can_fetch_thumbnail_with_preview_token(): void
    {
        Storage::fake('uploads');

        [$bundle, $file] = $this->createShareableBundleWithImage(ShareMode::StaticLink);

        $this->get("/bundle/{$bundle->slug}/file/{$file->uuid}/thumbnail?auth={$bundle->preview_token}")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_static_link_guest_rejected_with_wrong_token(): void
    {
        Storage::fake('uploads');

        [$bundle, $file] = $this->createShareableBundleWithImage(ShareMode::StaticLink);

        $this->get("/bundle/{$bundle->slug}/file/{$file->uuid}/thumbnail?auth=wrong-token")
            ->assertForbidden();
    }

    public function test_authenticated_non_owner_preview_uses_guest_thumbnail_urls_without_fullpath(): void
    {
        Storage::fake('uploads');
        config(['sso.enabled' => true]);

        [$bundle, $file] = $this->createShareableBundleWithImage(ShareMode::StaticLink);
        $viewer = User::factory()->create();

        $guestPath = "/bundle/{$bundle->slug}/file/{$file->uuid}/thumbnail";
        $ownerPath = "/upload/{$bundle->slug}/file/{$file->uuid}/thumbnail";

        $response = $this->actingAsUser($viewer)
            ->get("/bundle/{$bundle->slug}/preview?auth={$bundle->preview_token}")
            ->assertOk()
            ->assertDontSee($file->fullpath, false);

        $this->assertNotNull(
            $files = data_get($this->decodeBundlePayload($response->getContent()), 'files')
        );
        $payload = $this->decodeBundlePayload($response->getContent());
        $this->assertArrayNotHasKey('owner_token', $payload);
        $this->assertArrayNotHasKey('preview_token', $payload);
        $this->assertArrayNotHasKey('password', $payload);
        $this->assertCount(1, $payload['files']);
        $this->assertArrayNotHasKey('fullpath', $payload['files'][0]);
        $this->assertArrayNotHasKey('hash', $payload['files'][0]);
        $this->assertStringContainsString($guestPath, $payload['files'][0]['thumbnail_url']);
        $this->assertStringContainsString('auth='.$bundle->preview_token, $payload['files'][0]['thumbnail_url']);
        $this->assertStringNotContainsString($ownerPath, $payload['files'][0]['thumbnail_url']);

        $this->actingAsUser($viewer)
            ->get("{$guestPath}?auth={$bundle->preview_token}")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');

        $this->actingAsUser($viewer)
            ->get($ownerPath)
            ->assertForbidden();
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

    public function test_invitation_recipient_can_fetch_thumbnail_with_verified_session(): void
    {
        Storage::fake('uploads');

        [$bundle, $file] = $this->createShareableBundleWithImage(ShareMode::Invitation, BundleStatus::Sent);

        BundleRecipient::create([
            'bundle_id' => $bundle->id,
            'email' => 'guest@example.com',
            'invited_at' => now(),
            'verified_at' => now(),
        ]);

        $this->withSession(['recipient_access.'.$bundle->id => 'guest@example.com'])
            ->get("/bundle/{$bundle->slug}/file/{$file->uuid}/thumbnail")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_pending_approval_reviewer_can_fetch_thumbnail(): void
    {
        Storage::fake('uploads');

        $uploader = User::factory()->create();
        $reviewer = User::factory()->reviewer()->create();
        [$bundle, $file] = $this->createPendingBundleWithImage($uploader);

        $request = ApprovalRequest::create([
            'bundle_id' => $bundle->id,
            'requested_by' => $uploader->id,
            'status' => ApprovalRequestStatus::Pending,
        ]);

        $this->actingAsUser($reviewer)
            ->get(route('approval.file.thumbnail', [
                'approvalRequest' => $request,
                'file' => $file,
            ]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_non_reviewer_cannot_fetch_approval_thumbnail(): void
    {
        Storage::fake('uploads');

        $uploader = User::factory()->create();
        $other = User::factory()->create();
        [$bundle, $file] = $this->createPendingBundleWithImage($uploader);

        $request = ApprovalRequest::create([
            'bundle_id' => $bundle->id,
            'requested_by' => $uploader->id,
            'status' => ApprovalRequestStatus::Pending,
        ]);

        $this->actingAsUser($other)
            ->get(route('approval.file.thumbnail', [
                'approvalRequest' => $request,
                'file' => $file,
            ]))
            ->assertForbidden();
    }

    public function test_non_image_file_thumbnail_route_returns_not_found(): void
    {
        Storage::fake('uploads');

        $user = User::factory()->create(['requires_approval' => false]);
        $slug = 'no-thumb-'.Str::lower(Str::random(8));
        $this->slugs[] = $slug;

        $bundle = Bundle::create([
            'user_id' => $user->id,
            'slug' => $slug,
            'title' => 'No thumb',
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

        $file = File::create([
            'uuid' => (string) Str::uuid(),
            'bundle_id' => $bundle->id,
            'original' => 'notes.txt',
            'filesize' => 10,
            'fullpath' => "{$slug}/notes.txt",
            'filename' => 'notes',
            'status' => true,
            'thumbnail_path' => null,
        ]);

        Storage::disk('uploads')->put($file->fullpath, 'plain text');

        $this->get("/bundle/{$slug}/file/{$file->uuid}/thumbnail?auth={$bundle->preview_token}")
            ->assertNotFound();
    }

    /**
     * @return array{0: Bundle, 1: File}
     */
    private function createShareableBundleWithImage(
        ShareMode $shareMode,
        BundleStatus $status = BundleStatus::Approved,
    ): array {
        $user = User::factory()->create(['requires_approval' => false]);
        $slug = 'access-'.Str::lower(Str::random(8));
        $this->slugs[] = $slug;

        $bundle = Bundle::create([
            'user_id' => $user->id,
            'slug' => $slug,
            'title' => 'Shared images',
            'owner_token' => substr(sha1($slug.'owner'), 0, 15),
            'preview_token' => substr(sha1($slug.'preview'), 0, 15),
            'share_mode' => $shareMode,
            'require_otp' => $shareMode === ShareMode::Invitation,
            'completed' => true,
            'status' => $status,
            'expiry' => '86400',
            'expires_at' => now()->addDay(),
            'fullsize' => 100,
            'max_downloads' => 0,
            'downloads' => 0,
        ]);

        $file = $this->storeImageFile($bundle);

        return [$bundle, $file];
    }

    /**
     * @return array{0: Bundle, 1: File}
     */
    private function createPendingBundleWithImage(User $uploader): array
    {
        $slug = 'pending-'.Str::lower(Str::random(8));
        $this->slugs[] = $slug;

        $bundle = Bundle::create([
            'user_id' => $uploader->id,
            'slug' => $slug,
            'title' => 'Pending images',
            'owner_token' => substr(sha1($slug.'owner'), 0, 15),
            'preview_token' => substr(sha1($slug.'preview'), 0, 15),
            'share_mode' => ShareMode::StaticLink,
            'completed' => true,
            'status' => BundleStatus::PendingApproval,
            'expiry' => '86400',
            'fullsize' => 100,
            'max_downloads' => 0,
            'downloads' => 0,
        ]);

        $file = $this->storeImageFile($bundle);

        return [$bundle, $file];
    }

    private function storeImageFile(Bundle $bundle): File
    {
        $filename = 'img-'.Str::lower(Str::random(8));
        $fullpath = $bundle->slug.'/'.$filename;

        $image = imagecreatetruecolor(120, 80);
        $color = imagecolorallocate($image, 34, 139, 34);
        imagefilledrectangle($image, 0, 0, 120, 80, $color);
        ob_start();
        imagejpeg($image, null, 90);
        $contents = ob_get_clean();
        imagedestroy($image);

        Storage::disk('uploads')->put($fullpath, $contents);

        $file = File::create([
            'uuid' => (string) Str::uuid(),
            'bundle_id' => $bundle->id,
            'original' => 'photo.jpg',
            'filesize' => strlen($contents),
            'fullpath' => $fullpath,
            'filename' => $filename,
            'status' => true,
        ]);
        $file->setRelation('bundle', $bundle);
        $file->thumbnail_path = app(ImageThumbnailService::class)->generate($file);
        $file->save();

        $this->assertNotNull($file->thumbnail_path);

        return $file;
    }
}
