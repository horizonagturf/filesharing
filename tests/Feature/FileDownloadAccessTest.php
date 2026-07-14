<?php

namespace Tests\Feature;

use App\Enums\AuditEvent;
use App\Enums\BundleStatus;
use App\Enums\ShareMode;
use App\Models\Bundle;
use App\Models\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class FileDownloadAccessTest extends TestCase
{
    private array $slugs = [];

    protected function tearDown(): void
    {
        foreach ($this->slugs as $slug) {
            Storage::disk('uploads')->deleteDirectory($slug);
        }

        parent::tearDown();
    }

    public function test_guest_can_download_individual_file_with_preview_token(): void
    {
        [$bundle, $file, $contents] = $this->createShareableBundleWithFile();

        $response = $this->get("/bundle/{$bundle->slug}/file/{$file->uuid}/download?auth={$bundle->preview_token}");

        $response->assertOk();
        $this->assertSame($contents, $response->streamedContent());
        $this->assertSame(0, $bundle->fresh()->downloads);
        $this->assertDatabaseHas('audit_logs', [
            'event_type' => AuditEvent::FileDownloaded->value,
            'bundle_id' => $bundle->id,
            'file_id' => $file->id,
        ]);
    }

    public function test_guest_cannot_download_file_from_another_bundle(): void
    {
        [$bundleA, $fileA] = $this->createShareableBundleWithFile('cross-a');
        [$bundleB] = $this->createShareableBundleWithFile('cross-b');

        $this->get("/bundle/{$bundleB->slug}/file/{$fileA->uuid}/download?auth={$bundleB->preview_token}")
            ->assertNotFound();

        $this->assertSame(0, $bundleB->fresh()->downloads);
    }

    public function test_preview_payload_includes_guest_download_urls_when_unlimited(): void
    {
        [$bundle, $file] = $this->createShareableBundleWithFile('preview-dl');

        $response = $this->get("/bundle/{$bundle->slug}/preview?auth={$bundle->preview_token}")
            ->assertOk();

        $payload = $this->decodeBundlePayload($response->getContent());

        $this->assertStringContainsString(
            "/bundle/{$bundle->slug}/file/{$file->uuid}/download",
            $payload['files'][0]['download_url']
        );
        $this->assertStringContainsString('auth='.$bundle->preview_token, $payload['files'][0]['download_url']);
    }

    public function test_download_limit_hides_and_blocks_per_file_downloads(): void
    {
        [$bundle, $file, $contents] = $this->createShareableBundleWithFile('limited-dl', maxDownloads: 2);

        $response = $this->get("/bundle/{$bundle->slug}/preview?auth={$bundle->preview_token}")
            ->assertOk();

        $payload = $this->decodeBundlePayload($response->getContent());
        $this->assertNull($payload['files'][0]['download_url'] ?? null);

        $this->get("/bundle/{$bundle->slug}/file/{$file->uuid}/download?auth={$bundle->preview_token}")
            ->assertForbidden();

        $this->assertSame(0, $bundle->fresh()->downloads);

        Storage::disk('uploads')->put($file->fullpath, $contents);

        $this->get("/bundle/{$bundle->slug}/download?auth={$bundle->preview_token}")
            ->assertOk();

        $this->assertSame(1, $bundle->fresh()->downloads);
    }

    public function test_password_required_blocks_downloads_until_unlocked(): void
    {
        [$bundle, $file, $contents] = $this->createShareableBundleWithFile('pw-dl', password: 's3cret');

        $this->get("/bundle/{$bundle->slug}/file/{$file->uuid}/download?auth={$bundle->preview_token}")
            ->assertRedirect(route('bundle.preview', ['bundle' => $bundle, 'auth' => $bundle->preview_token]));

        $this->get("/bundle/{$bundle->slug}/download?auth={$bundle->preview_token}")
            ->assertRedirect(route('bundle.preview', ['bundle' => $bundle, 'auth' => $bundle->preview_token]));

        $preview = $this->get("/bundle/{$bundle->slug}/preview?auth={$bundle->preview_token}")
            ->assertOk();
        $payload = $this->decodeBundlePayload($preview->getContent());
        $this->assertTrue($payload['password_required']);
        $this->assertFalse($payload['password_unlocked']);
        $this->assertArrayNotHasKey('password', $payload);

        $this->postJson("/bundle/{$bundle->slug}/unlock?auth={$bundle->preview_token}", [
            'password' => 'wrong',
        ])->assertStatus(422);

        $this->postJson("/bundle/{$bundle->slug}/unlock?auth={$bundle->preview_token}", [
            'password' => 's3cret',
        ])->assertOk()->assertJson([
            'result' => true,
            'password_unlocked' => true,
        ]);

        $unlocked = $this->get("/bundle/{$bundle->slug}/file/{$file->uuid}/download?auth={$bundle->preview_token}");
        $unlocked->assertOk();
        $this->assertSame($contents, $unlocked->streamedContent());
    }

    /**
     * @return array{0: Bundle, 1: File, 2: string}
     */
    private function createShareableBundleWithFile(
        string $prefix = 'file-dl',
        int $maxDownloads = 0,
        ?string $password = null,
    ): array {
        $slug = $prefix.'-'.Str::random(6);
        $this->slugs[] = $slug;

        $bundle = Bundle::create([
            'slug' => $slug,
            'title' => 'File Download',
            'owner_token' => substr(sha1('owner-'.$slug), 0, 15),
            'preview_token' => substr(sha1('preview-'.$slug), 0, 15),
            'share_mode' => ShareMode::StaticLink,
            'password' => $password,
            'completed' => true,
            'status' => BundleStatus::Approved,
            'expiry' => '86400',
            'expires_at' => now()->addDay(),
            'fullsize' => 0,
            'max_downloads' => $maxDownloads,
            'downloads' => 0,
        ]);

        $contents = 'hello-file-'.$slug;
        $filename = (string) Str::uuid();
        $fullpath = "{$slug}/{$filename}";
        Storage::disk('uploads')->put($fullpath, $contents);

        $file = File::create([
            'uuid' => (string) Str::uuid(),
            'bundle_id' => $bundle->id,
            'original' => 'notes.txt',
            'filesize' => strlen($contents),
            'fullpath' => $fullpath,
            'filename' => $filename,
            'status' => true,
        ]);

        return [$bundle, $file, $contents];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeBundlePayload(string $html): array
    {
        $this->assertSame(1, preg_match("/window\.__bundle = JSON\.parse\('(.*)'\);/s", $html, $matches));
        $json = json_decode('"'.$matches[1].'"');

        return json_decode($json, true);
    }
}
