<?php

namespace Tests\Feature;

use App\Enums\AuditEvent;
use App\Enums\BundleStatus;
use App\Enums\ShareMode;
use App\Models\Bundle;
use App\Models\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Tests\TestCase;
use ZipArchive;

class BundleZipDownloadTest extends TestCase
{
    private ?string $slug = null;

    protected function tearDown(): void
    {
        if ($this->slug !== null) {
            Storage::disk('uploads')->deleteDirectory($this->slug);
        }

        parent::tearDown();
    }

    public function test_single_unprotected_zip_is_served_without_rezipping(): void
    {
        $originalBytes = $this->makeZipBytes(['notes.txt' => 'hello']);
        $bundle = $this->createApprovedBundle('passthru-zip');
        $this->addFile($bundle, 'report.zip', $originalBytes);

        $response = $this->get("/bundle/{$bundle->slug}/download?auth={$bundle->preview_token}");

        $response->assertOk();
        $this->assertSame($originalBytes, $response->streamedContent());
        $this->assertStringContainsString('report.zip', $response->headers->get('content-disposition'));
        $this->assertFalse(Storage::disk('uploads')->exists("{$bundle->slug}/bundle.zip"));
        $this->assertSame(1, $bundle->fresh()->downloads);
        $this->assertDatabaseHas('audit_logs', [
            'event_type' => AuditEvent::BundleZipDownloaded->value,
            'bundle_id' => $bundle->id,
        ]);
    }

    public function test_single_zip_with_password_is_rezipped(): void
    {
        $originalBytes = $this->makeZipBytes(['notes.txt' => 'hello']);
        $bundle = $this->createApprovedBundle('password-zip', password: 'secret');
        $this->addFile($bundle, 'report.zip', $originalBytes);

        $this->get("/bundle/{$bundle->slug}/download?auth={$bundle->preview_token}")
            ->assertRedirect(route('bundle.preview', ['bundle' => $bundle, 'auth' => $bundle->preview_token]));

        $this->postJson("/bundle/{$bundle->slug}/unlock?auth={$bundle->preview_token}", [
            'password' => 'secret',
        ])->assertOk();

        $response = $this->get("/bundle/{$bundle->slug}/download?auth={$bundle->preview_token}");

        $response->assertOk();
        $this->assertNotSame($originalBytes, $response->streamedContent());
        $this->assertTrue(Storage::disk('uploads')->exists("{$bundle->slug}/bundle.zip"));

        $archive = new ZipArchive;
        $this->assertTrue($archive->open(Storage::disk('uploads')->path("{$bundle->slug}/bundle.zip")));
        $this->assertSame(1, $archive->numFiles);
        $this->assertSame('report.zip', $archive->getNameIndex(0));
        $this->assertTrue($archive->setPassword('secret'));
        $this->assertNotFalse($archive->getFromName('report.zip'));
        $archive->close();
    }

    public function test_single_non_zip_file_is_rezipped(): void
    {
        $content = 'plain text content';
        $bundle = $this->createApprovedBundle('single-txt');
        $this->addFile($bundle, 'notes.txt', $content);

        $response = $this->get("/bundle/{$bundle->slug}/download?auth={$bundle->preview_token}");

        $response->assertOk();
        $this->assertNotSame($content, $response->streamedContent());
        $this->assertTrue(Storage::disk('uploads')->exists("{$bundle->slug}/bundle.zip"));

        $archive = new ZipArchive;
        $this->assertTrue($archive->open(Storage::disk('uploads')->path("{$bundle->slug}/bundle.zip")));
        $this->assertSame(1, $archive->numFiles);
        $this->assertSame('notes.txt', $archive->getNameIndex(0));
        $this->assertSame($content, $archive->getFromIndex(0));
        $archive->close();
    }

    public function test_multiple_files_including_zip_are_rezipped(): void
    {
        $zipBytes = $this->makeZipBytes(['inner.txt' => 'nested']);
        $bundle = $this->createApprovedBundle('multi-files');
        $this->addFile($bundle, 'archive.zip', $zipBytes);
        $this->addFile($bundle, 'readme.txt', 'readme');

        $response = $this->get("/bundle/{$bundle->slug}/download?auth={$bundle->preview_token}");

        $response->assertOk();
        $this->assertTrue(Storage::disk('uploads')->exists("{$bundle->slug}/bundle.zip"));

        $archive = new ZipArchive;
        $this->assertTrue($archive->open(Storage::disk('uploads')->path("{$bundle->slug}/bundle.zip")));
        $this->assertSame(2, $archive->numFiles);
        $names = [$archive->getNameIndex(0), $archive->getNameIndex(1)];
        sort($names);
        $this->assertSame(['archive.zip', 'readme.txt'], $names);
        $archive->close();
    }

    public function test_passthrough_zip_sanitizes_content_disposition_filename(): void
    {
        $maliciousName = 'Q4"; filename="evil.zip';
        $originalBytes = $this->makeZipBytes(['notes.txt' => 'hello']);
        $bundle = $this->createApprovedBundle('evil-name-zip');
        $this->addFile($bundle, $maliciousName, $originalBytes);

        $response = $this->get("/bundle/{$bundle->slug}/download?auth={$bundle->preview_token}");

        $response->assertOk();
        $this->assertSame($originalBytes, $response->streamedContent());

        $disposition = $response->headers->get('content-disposition');
        $this->assertNotNull($disposition);
        $this->assertStringStartsWith('attachment;', $disposition);
        $this->assertStringNotContainsString('filename="evil.zip"', $disposition);
        $this->assertSame(
            HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_ATTACHMENT,
                $maliciousName,
                $maliciousName
            ),
            $disposition
        );
    }

    private function createApprovedBundle(string $slug, ?string $password = null): Bundle
    {
        $this->slug = $slug;

        return Bundle::create([
            'slug' => $slug,
            'title' => 'Download Test',
            'owner_token' => substr(sha1('owner-'.$slug), 0, 15),
            'preview_token' => substr(sha1('preview-'.$slug), 0, 15),
            'share_mode' => ShareMode::StaticLink,
            'password' => $password,
            'completed' => true,
            'status' => BundleStatus::Approved,
            'expiry' => '86400',
            'expires_at' => now()->addDay(),
            'fullsize' => 0,
            'max_downloads' => 0,
            'downloads' => 0,
        ]);
    }

    private function addFile(Bundle $bundle, string $original, string $contents): File
    {
        $filename = (string) Str::uuid();
        $fullpath = "{$bundle->slug}/{$filename}";
        Storage::disk('uploads')->put($fullpath, $contents);

        return File::create([
            'uuid' => (string) Str::uuid(),
            'bundle_id' => $bundle->id,
            'original' => $original,
            'filesize' => strlen($contents),
            'fullpath' => $fullpath,
            'filename' => $filename,
            'status' => true,
        ]);
    }

    private function makeZipBytes(array $entries): string
    {
        $path = tempnam(sys_get_temp_dir(), 'fszip');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        foreach ($entries as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        $bytes = file_get_contents($path);
        @unlink($path);

        return $bytes;
    }
}
