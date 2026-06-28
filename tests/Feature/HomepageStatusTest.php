<?php

namespace Tests\Feature;

use App\Enums\BundleStatus;
use App\Models\Bundle;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

class HomepageStatusTest extends TestCase
{
    public function test_homepage_includes_bundle_status_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $slug = 'status-'.Str::lower(Str::random(8));

        Bundle::create([
            'user_id' => $user->id,
            'slug' => $slug,
            'title' => 'Status test bundle',
            'owner_token' => substr(sha1($slug.'owner'), 0, 15),
            'preview_token' => substr(sha1($slug.'preview'), 0, 15),
            'completed' => true,
            'status' => BundleStatus::PendingApproval,
            'expiry' => '86400',
            'fullsize' => 0,
            'max_downloads' => 0,
            'downloads' => 0,
        ]);

        $response = $this->actingAsUser($user)
            ->get(route('homepage'))
            ->assertOk()
            ->assertSee('pending_approval', false)
            ->assertSee(__('approval.status-pending_approval'), false);

        $this->assertMatchesRegularExpression(
            '/window\.__bundles = JSON\.parse\(\'\[\{/',
            $response->getContent(),
        );
    }

    public function test_homepage_includes_denied_status_label(): void
    {
        $user = User::factory()->create();
        $slug = 'denied-'.Str::lower(Str::random(8));

        Bundle::create([
            'user_id' => $user->id,
            'slug' => $slug,
            'title' => 'Denied bundle',
            'owner_token' => substr(sha1($slug.'owner'), 0, 15),
            'preview_token' => substr(sha1($slug.'preview'), 0, 15),
            'completed' => false,
            'status' => BundleStatus::Denied,
            'expiry' => '86400',
            'fullsize' => 0,
            'max_downloads' => 0,
            'downloads' => 0,
        ]);

        $this->actingAsUser($user)
            ->get(route('homepage'))
            ->assertOk()
            ->assertSee('denied', false)
            ->assertSee(__('approval.status-denied'), false);
    }
}
