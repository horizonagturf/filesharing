<?php

namespace Tests\Feature;

use App\Enums\ApprovalRequestStatus;
use App\Enums\AuditEvent;
use App\Enums\BundleStatus;
use App\Enums\ShareMode;
use App\Mail\ApprovalRequestSubmittedMail;
use App\Models\ApprovalRequest;
use App\Models\Bundle;
use App\Models\File;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SelfReviewAndPurgeTest extends TestCase
{
    // --- Reviewers can't review their own bundles ---------------------------

    public function test_reviewer_cannot_approve_own_bundle(): void
    {
        $reviewer = User::factory()->reviewer()->create();
        $request = $this->pendingRequest($reviewer);

        $this->actingAsUser($reviewer)
            ->postJson(route('approval.approve', $request), [], $this->ajax())
            ->assertStatus(422)
            ->assertJsonPath('message', __('approval.cannot-review-own'));

        $this->assertSame(BundleStatus::PendingApproval, $request->bundle->fresh()->status);
        $this->assertTrue($request->fresh()->isPending());
    }

    public function test_reviewer_cannot_deny_own_bundle(): void
    {
        $reviewer = User::factory()->reviewer()->create();
        $request = $this->pendingRequest($reviewer);

        $this->actingAsUser($reviewer)
            ->postJson(route('approval.deny', $request), ['reason' => 'Changed my mind'], $this->ajax())
            ->assertStatus(422);

        $this->assertTrue($request->fresh()->isPending());
    }

    public function test_admin_cannot_approve_own_bundle(): void
    {
        $admin = User::factory()->admin()->create();
        $request = $this->pendingRequest($admin);

        $this->actingAsUser($admin)
            ->postJson(route('approval.approve', $request), [], $this->ajax())
            ->assertStatus(422);

        $this->assertTrue($request->fresh()->isPending());
    }

    public function test_own_bundle_is_hidden_from_queue_and_show_page(): void
    {
        $reviewer = User::factory()->reviewer()->create();
        $mine = $this->pendingRequest($reviewer, 'My own bundle');
        $theirs = $this->pendingRequest(User::factory()->create(), 'Someone elses bundle');

        $this->actingAsUser($reviewer)
            ->get(route('approval.index'))
            ->assertOk()
            ->assertSee('Someone elses bundle')
            ->assertDontSee('My own bundle');

        $this->actingAsUser($reviewer)->get(route('approval.show', $mine))->assertForbidden();
        $this->actingAsUser($reviewer)->get(route('approval.show', $theirs))->assertOk();
    }

    public function test_another_reviewer_can_approve(): void
    {
        $owner = User::factory()->reviewer()->create();
        $other = User::factory()->reviewer()->create();
        $request = $this->pendingRequest($owner);

        $this->actingAsUser($other)
            ->postJson(route('approval.approve', $request), [], $this->ajax())
            ->assertOk();

        $this->assertSame(BundleStatus::Approved, $request->bundle->fresh()->status);
    }

    public function test_reviewer_is_not_emailed_about_own_submission(): void
    {
        Mail::fake();

        $owner = User::factory()->reviewer()->create(['email' => 'owner@example.com', 'requires_approval' => true]);
        User::factory()->reviewer()->create(['email' => 'other@example.com']);
        $bundle = $this->bundle($owner, [
            'status' => BundleStatus::Draft,
            'completed' => false,
            'expires_at' => null,
        ]);

        $this->actingAsUser($owner)
            ->postJson("/upload/{$bundle->slug}/complete", [], [
                'X-Upload-Auth' => $bundle->owner_token,
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->assertOk()
            ->assertJsonPath('status', BundleStatus::PendingApproval->value);

        Mail::assertQueued(ApprovalRequestSubmittedMail::class, fn ($mail) => $mail->hasTo('other@example.com'));
        Mail::assertNotQueued(ApprovalRequestSubmittedMail::class, fn ($mail) => $mail->hasTo('owner@example.com'));
    }

    // --- Purge removes abandoned drafts and old denied bundles --------------

    public function test_purge_removes_abandoned_draft(): void
    {
        Storage::fake('uploads');
        $bundle = $this->bundle(null, ['status' => BundleStatus::Draft, 'completed' => false, 'expires_at' => null]);
        $this->age($bundle, now()->subHours(73));

        Artisan::call('fs:bundle:purge');

        $this->assertNull(Bundle::find($bundle->id));
        $this->assertSame(0, File::where('bundle_id', $bundle->id)->count());
        $this->assertFalse(Storage::disk('uploads')->exists($bundle->slug));

        $log = DB::table('audit_logs')->where('event_type', AuditEvent::BundlePurged->value)->first();
        $this->assertNotNull($log);
        $this->assertSame('system', $log->actor_type);
        $this->assertSame('abandoned_draft', json_decode($log->metadata, true)['reason']);
        $this->assertSame($bundle->slug, json_decode($log->metadata, true)['slug']);
    }

    public function test_purge_keeps_recent_draft(): void
    {
        Storage::fake('uploads');
        $bundle = $this->bundle(null, ['status' => BundleStatus::Draft, 'completed' => false, 'expires_at' => null]);
        $this->age($bundle, now()->subHours(10));

        Artisan::call('fs:bundle:purge');

        $this->assertNotNull(Bundle::find($bundle->id));
    }

    public function test_purge_keeps_old_draft_with_recent_upload(): void
    {
        Storage::fake('uploads');
        $bundle = $this->bundle(null, ['status' => BundleStatus::Draft, 'completed' => false, 'expires_at' => null]);
        $this->age($bundle, now()->subDays(10), filesAt: now()->subHour());

        Artisan::call('fs:bundle:purge');

        $this->assertNotNull(Bundle::find($bundle->id));
    }

    public function test_purge_keeps_legacy_completed_draft_bundle(): void
    {
        Storage::fake('uploads');
        // Shared before the approval workflow existed: draft status, but completed.
        $bundle = $this->bundle(null, ['status' => BundleStatus::Draft, 'completed' => true, 'expires_at' => null]);
        $this->age($bundle, now()->subDays(30));

        Artisan::call('fs:bundle:purge');

        $this->assertNotNull(Bundle::find($bundle->id));
    }

    public function test_purge_removes_old_denied_bundle_and_keeps_recent_one(): void
    {
        Storage::fake('uploads');
        $old = $this->bundle(null, ['status' => BundleStatus::Denied, 'completed' => false, 'expires_at' => null]);
        $this->age($old, now()->subDays(15));
        $recent = $this->bundle(null, ['status' => BundleStatus::Denied, 'completed' => false, 'expires_at' => null]);
        $this->age($recent, now()->subDays(2));

        Artisan::call('fs:bundle:purge');

        $this->assertNull(Bundle::find($old->id));
        $this->assertNotNull(Bundle::find($recent->id));
    }

    public function test_purge_keeps_pending_approval_bundle(): void
    {
        Storage::fake('uploads');
        $bundle = $this->bundle(null, ['status' => BundleStatus::PendingApproval, 'completed' => true, 'expires_at' => null]);
        $this->age($bundle, now()->subDays(60));

        Artisan::call('fs:bundle:purge');

        $this->assertNotNull(Bundle::find($bundle->id));
    }

    public function test_zero_retention_disables_draft_and_denied_purge(): void
    {
        Storage::fake('uploads');
        config(['sharing.draft_retention_hours' => 0, 'sharing.denied_retention_days' => 0]);

        $draft = $this->bundle(null, ['status' => BundleStatus::Draft, 'completed' => false, 'expires_at' => null]);
        $this->age($draft, now()->subDays(60));
        $denied = $this->bundle(null, ['status' => BundleStatus::Denied, 'completed' => false, 'expires_at' => null]);
        $this->age($denied, now()->subDays(60));

        Artisan::call('fs:bundle:purge');

        $this->assertNotNull(Bundle::find($draft->id));
        $this->assertNotNull(Bundle::find($denied->id));
    }

    public function test_purge_still_removes_expired_bundles(): void
    {
        Storage::fake('uploads');
        $expired = $this->bundle(null, ['expires_at' => now()->subMinute()]);
        $live = $this->bundle(null, ['expires_at' => now()->addDay()]);

        Artisan::call('fs:bundle:purge');

        $this->assertNull(Bundle::find($expired->id));
        $this->assertNotNull(Bundle::find($live->id));
    }

    // --- Helpers ------------------------------------------------------------

    private function ajax(): array
    {
        return ['X-Requested-With' => 'XMLHttpRequest'];
    }

    private function pendingRequest(User $owner, string $title = 'Pending bundle'): ApprovalRequest
    {
        $bundle = $this->bundle($owner, [
            'title' => $title,
            'status' => BundleStatus::PendingApproval,
            'expires_at' => null,
        ]);

        return ApprovalRequest::create([
            'bundle_id' => $bundle->id,
            'requested_by' => $owner->id,
            'status' => ApprovalRequestStatus::Pending,
        ]);
    }

    private function bundle(?User $owner, array $attributes = []): Bundle
    {
        $slug = 'srp-'.Str::lower(Str::random(10));

        $bundle = Bundle::create(array_merge([
            'user_id' => $owner?->id,
            'slug' => $slug,
            'title' => 'Bundle',
            'owner_token' => Str::lower(Str::random(32)),
            'preview_token' => Str::lower(Str::random(32)),
            'share_mode' => ShareMode::StaticLink,
            'require_otp' => true,
            'completed' => true,
            'status' => BundleStatus::Approved,
            'expiry' => '86400',
            'expires_at' => now()->addDay(),
            'fullsize' => 4,
            'max_downloads' => 0,
            'downloads' => 0,
        ], $attributes));

        File::create([
            'uuid' => (string) Str::uuid(),
            'bundle_id' => $bundle->id,
            'original' => 'doc.txt',
            'filesize' => 4,
            'fullpath' => "{$slug}/doc.txt",
            'filename' => 'doc',
            'status' => true,
        ]);

        Storage::disk('uploads')->put("{$slug}/doc.txt", 'data');

        return $bundle;
    }

    private function age(Bundle $bundle, $at, $filesAt = null): void
    {
        DB::table('bundles')->where('id', $bundle->id)->update(['updated_at' => $at, 'created_at' => $at]);
        DB::table('files')->where('bundle_id', $bundle->id)->update([
            'created_at' => $filesAt ?? $at,
            'updated_at' => $filesAt ?? $at,
        ]);
    }
}
