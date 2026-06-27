<?php

namespace Tests\Feature;

use App\Enums\BundleStatus;
use App\Models\Bundle;
use App\Models\File;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

class LegacyCompletedBundleTest extends TestCase
{
    public function test_legacy_completed_draft_bundle_is_shareable(): void
    {
        $bundle = $this->createLegacyBundle();

        $this->assertTrue($bundle->isShareable());
        $this->assertFalse($bundle->isEditable());
    }

    public function test_guest_can_access_legacy_completed_draft_bundle(): void
    {
        $bundle = $this->createLegacyBundle();

        $this->get("/bundle/{$bundle->slug}/preview?auth={$bundle->preview_token}")
            ->assertOk()
            ->assertSee('Legacy bundle');
    }

    public function test_migration_promotes_legacy_completed_drafts_to_sent(): void
    {
        $this->artisan('migrate:rollback', [
            '--path' => 'database/migrations/2026_06_27_000004_migrate_legacy_completed_bundle_status.php',
        ])->assertSuccessful();

        $bundle = $this->createLegacyBundle();
        $this->assertSame(BundleStatus::Draft, $bundle->status);

        $this->artisan('migrate', [
            '--path' => 'database/migrations/2026_06_27_000004_migrate_legacy_completed_bundle_status.php',
        ])->assertSuccessful();

        $bundle->refresh();
        $this->assertSame(BundleStatus::Sent, $bundle->status);
        $this->assertTrue($bundle->isShareable());
    }

    private function createLegacyBundle(): Bundle
    {
        $slug = 'legacy-'.Str::lower(Str::random(8));
        $user = User::factory()->create();
        $previewToken = substr(sha1($slug.'preview'), 0, 15);

        $bundle = Bundle::create([
            'user_id' => $user->id,
            'slug' => $slug,
            'title' => 'Legacy bundle',
            'owner_token' => substr(sha1($slug.'owner'), 0, 15),
            'preview_token' => $previewToken,
            'completed' => true,
            'status' => BundleStatus::Draft,
            'expiry' => '86400',
            'fullsize' => 100,
            'max_downloads' => 0,
            'downloads' => 0,
        ]);

        $bundle->update([
            'preview_link' => route('bundle.preview', ['bundle' => $bundle, 'auth' => $previewToken]),
            'download_link' => route('bundle.zip.download', ['bundle' => $bundle, 'auth' => $previewToken]),
        ]);

        File::create([
            'uuid' => (string) Str::uuid(),
            'bundle_id' => $bundle->id,
            'original' => 'legacy.txt',
            'filename' => 'legacy.txt',
            'fullpath' => "{$slug}/legacy.txt",
            'filesize' => 100,
            'status' => true,
        ]);

        return $bundle;
    }
}
