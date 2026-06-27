<?php

namespace Tests\Feature\Filament;

use App\Enums\BundleStatus;
use App\Filament\Resources\BundleResource\Pages\ListBundles;
use App\Filament\Resources\BundleResource\Pages\ViewBundle;
use App\Models\Bundle;
use App\Models\File;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AdminBundleResourceTest extends TestCase
{
    private ?string $slug = null;

    protected function tearDown(): void
    {
        if ($this->slug !== null) {
            if ($bundle = Bundle::where('slug', $this->slug)->first()) {
                foreach ($bundle->files as $file) {
                    $file->delete();
                }
                $bundle->delete();
            }
            Storage::disk('uploads')->deleteDirectory($this->slug);
        }

        parent::tearDown();
    }

    public function test_admin_can_see_bundles_in_table(): void
    {
        $admin = User::factory()->admin()->create();
        $bundle = $this->createBundle(['status' => BundleStatus::Draft]);

        Livewire::actingAs($admin)
            ->test(ListBundles::class)
            ->assertCanSeeTableRecords([$bundle])
            ->searchTable($bundle->slug)
            ->assertCanSeeTableRecords([$bundle]);
    }

    public function test_admin_can_filter_bundles_by_status(): void
    {
        $admin = User::factory()->admin()->create();
        $draft = $this->createBundle(['status' => BundleStatus::Draft]);
        $sent = $this->createBundle(['status' => BundleStatus::Sent]);

        Livewire::actingAs($admin)
            ->test(ListBundles::class)
            ->filterTable('status', BundleStatus::Draft->value)
            ->assertCanSeeTableRecords([$draft])
            ->assertCanNotSeeTableRecords([$sent]);
    }

    public function test_admin_can_revoke_bundle_from_view_page(): void
    {
        $admin = User::factory()->admin()->create();
        $bundle = $this->createBundle(['status' => BundleStatus::Approved]);

        Livewire::actingAs($admin)
            ->test(ViewBundle::class, ['record' => $bundle->getRouteKey()])
            ->callAction('revoke')
            ->assertNotified();

        $this->assertSame(BundleStatus::Revoked, $bundle->fresh()->status);
    }

    public function test_admin_can_extend_bundle_expiry_from_view_page(): void
    {
        $admin = User::factory()->admin()->create();
        $bundle = $this->createBundle([
            'expires_at' => null,
            'expiry' => 'forever',
        ]);

        Livewire::actingAs($admin)
            ->test(ViewBundle::class, ['record' => $bundle->getRouteKey()])
            ->callAction('extendExpiry', data: ['days' => 14])
            ->assertNotified();

        $this->assertNotNull($bundle->fresh()->expires_at);
        $this->assertTrue($bundle->fresh()->expires_at->isFuture());
    }

    public function test_admin_can_delete_bundle_from_view_page(): void
    {
        $admin = User::factory()->admin()->create();
        $bundle = $this->createBundle();
        $file = $bundle->files()->first();
        Storage::disk('uploads')->put($file->fullpath, 'content');

        Livewire::actingAs($admin)
            ->test(ViewBundle::class, ['record' => $bundle->getRouteKey()])
            ->callAction('deletePermanently')
            ->assertNotified()
            ->assertRedirect();

        $this->assertNull(Bundle::find($bundle->id));
        $this->assertFalse(Storage::disk('uploads')->exists($file->fullpath));

        $this->slug = null;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createBundle(array $overrides = []): Bundle
    {
        $this->slug = 'filament'.Str::lower(Str::random(8));

        $owner = User::factory()->create();

        $bundle = Bundle::create(array_merge([
            'user_id' => $owner->id,
            'slug' => $this->slug,
            'owner_token' => substr(sha1('owner'), 0, 15),
            'preview_token' => substr(sha1('preview'), 0, 15),
            'completed' => true,
            'expiry' => '86400',
            'expires_at' => now()->addDay(),
            'fullsize' => 1024,
            'max_downloads' => 0,
            'downloads' => 0,
            'status' => BundleStatus::Draft,
        ], $overrides));

        File::create([
            'uuid' => (string) Str::uuid(),
            'bundle_id' => $bundle->id,
            'original' => 'report.pdf',
            'filesize' => 1024,
            'fullpath' => $bundle->slug.'/report.pdf',
            'filename' => 'report.pdf',
            'status' => true,
        ]);

        return $bundle;
    }
}
