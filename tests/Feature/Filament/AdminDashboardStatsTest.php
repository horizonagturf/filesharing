<?php

namespace Tests\Feature\Filament;

use App\Enums\ApprovalRequestStatus;
use App\Enums\BundleStatus;
use App\Filament\Widgets\AdminStatsOverview;
use App\Models\ApprovalRequest;
use App\Models\Bundle;
use App\Models\User;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AdminDashboardStatsTest extends TestCase
{
    public function test_admin_dashboard_shows_organization_stats(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->count(2)->create();

        $uploader = User::factory()->create();
        $pendingBundle = Bundle::create([
            'user_id' => $uploader->id,
            'slug' => 'pending-'.Str::lower(Str::random(8)),
            'owner_token' => substr(sha1('owner'), 0, 15),
            'preview_token' => substr(sha1('preview'), 0, 15),
            'completed' => true,
            'status' => BundleStatus::PendingApproval,
            'expiry' => '86400',
            'fullsize' => 0,
            'max_downloads' => 0,
            'downloads' => 0,
        ]);

        ApprovalRequest::create([
            'bundle_id' => $pendingBundle->id,
            'requested_by' => $uploader->id,
            'status' => ApprovalRequestStatus::Pending,
        ]);

        Bundle::create([
            'user_id' => $uploader->id,
            'slug' => 'sent-'.Str::lower(Str::random(8)),
            'owner_token' => substr(sha1('owner2'), 0, 15),
            'preview_token' => substr(sha1('preview2'), 0, 15),
            'completed' => true,
            'status' => BundleStatus::Sent,
            'expiry' => '86400',
            'expires_at' => now()->addDay(),
            'fullsize' => 0,
            'max_downloads' => 0,
            'downloads' => 5,
        ]);

        Bundle::create([
            'user_id' => $uploader->id,
            'slug' => 'expired-'.Str::lower(Str::random(8)),
            'owner_token' => substr(sha1('owner3'), 0, 15),
            'preview_token' => substr(sha1('preview3'), 0, 15),
            'completed' => true,
            'status' => BundleStatus::Approved,
            'expiry' => '86400',
            'expires_at' => now()->subDay(),
            'fullsize' => 0,
            'max_downloads' => 0,
            'downloads' => 3,
        ]);

        Livewire::actingAs($admin)
            ->test(AdminStatsOverview::class)
            ->assertSee('Users')
            ->assertSee('3')
            ->assertSee('Pending approval')
            ->assertSee('1')
            ->assertSee('Published bundles')
            ->assertSee('2')
            ->assertSee('Active bundles')
            ->assertSee('Total downloads')
            ->assertSee('8');
    }
}
