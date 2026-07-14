<?php

namespace Tests\Feature;

use App\Enums\AuditEvent;
use App\Enums\BundleStatus;
use App\Enums\ShareMode;
use App\Models\AuditLog;
use App\Models\Bundle;
use Illuminate\Support\Str;
use Tests\TestCase;

class BundleUnavailableAccessTest extends TestCase
{
    public function test_expired_bundle_shows_dedicated_unavailable_page(): void
    {
        $bundle = Bundle::create([
            'slug' => 'expired-page-'.Str::random(6),
            'owner_token' => substr(sha1('owner'), 0, 15),
            'preview_token' => substr(sha1('preview'), 0, 15),
            'share_mode' => ShareMode::StaticLink,
            'completed' => true,
            'status' => BundleStatus::Approved,
            'expiry' => '86400',
            'expires_at' => now()->subMinute(),
            'fullsize' => 0,
            'max_downloads' => 0,
            'downloads' => 0,
        ]);

        $this->get("/bundle/{$bundle->slug}/preview?auth={$bundle->preview_token}")
            ->assertStatus(410)
            ->assertSee(__('app.bundle-unavailable-expired'), false);

        $log = AuditLog::query()
            ->where('event_type', AuditEvent::AccessDenied->value)
            ->where('bundle_id', $bundle->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('bundle_expired', $log->metadata['reason']);
        $this->assertSame(410, $log->metadata['status']);
    }

    public function test_max_downloads_shows_dedicated_unavailable_page(): void
    {
        $bundle = Bundle::create([
            'slug' => 'maxdl-page-'.Str::random(6),
            'owner_token' => substr(sha1('owner'), 0, 15),
            'preview_token' => substr(sha1('preview'), 0, 15),
            'share_mode' => ShareMode::StaticLink,
            'completed' => true,
            'status' => BundleStatus::Approved,
            'expiry' => '86400',
            'expires_at' => now()->addDay(),
            'fullsize' => 0,
            'max_downloads' => 1,
            'downloads' => 1,
        ]);

        $this->get("/bundle/{$bundle->slug}/preview?auth={$bundle->preview_token}")
            ->assertStatus(410)
            ->assertSee(__('app.bundle-unavailable-max-downloads'), false);

        $log = AuditLog::query()
            ->where('event_type', AuditEvent::AccessDenied->value)
            ->where('bundle_id', $bundle->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('max_downloads_exceeded', $log->metadata['reason']);
        $this->assertSame(410, $log->metadata['status']);
    }
}
