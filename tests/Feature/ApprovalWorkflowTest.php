<?php

namespace Tests\Feature;

use App\Enums\ApprovalRequestStatus;
use App\Enums\BundleStatus;
use App\Mail\ApprovalRequestSubmittedMail;
use App\Mail\BundleApprovedMail;
use App\Mail\BundleDeniedMail;
use App\Models\ApprovalRequest;
use App\Models\Bundle;
use App\Models\File;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApprovalWorkflowTest extends TestCase
{
    public function test_approval_required_user_submits_for_review_without_links(): void
    {
        Mail::fake();
        config(['approval.required_default' => true]);

        $user = User::factory()->create(['requires_approval' => null]);
        $reviewer = User::factory()->reviewer()->create();
        $bundle = $this->createBundle($user);

        $this->actingAsUser($user)
            ->postJson("/upload/{$bundle->slug}/complete", [
                'auth' => $bundle->owner_token,
            ], $this->uploadHeaders($bundle))
            ->assertOk()
            ->assertJsonPath('status', BundleStatus::PendingApproval->value)
            ->assertJsonPath('preview_link', null)
            ->assertJsonPath('download_link', null);

        $bundle->refresh();
        $this->assertTrue($bundle->completed);
        $this->assertDatabaseHas('approval_requests', [
            'bundle_id' => $bundle->id,
            'requested_by' => $user->id,
            'status' => ApprovalRequestStatus::Pending->value,
        ]);

        Mail::assertSent(ApprovalRequestSubmittedMail::class, fn ($mail) => $mail->hasTo($reviewer->email));
    }

    public function test_pending_approval_bundle_has_no_expires_at(): void
    {
        Mail::fake();
        config(['approval.required_default' => true]);

        $user = User::factory()->create(['requires_approval' => null]);
        $bundle = $this->createBundle($user);

        $this->actingAsUser($user)
            ->postJson("/upload/{$bundle->slug}/complete", [
                'auth' => $bundle->owner_token,
            ], $this->uploadHeaders($bundle))
            ->assertOk();

        $this->assertNull($bundle->fresh()->expires_at);
    }

    public function test_approved_bundle_expiry_starts_at_approval_not_submit(): void
    {
        Mail::fake();
        config(['approval.required_default' => true]);

        $uploader = User::factory()->create(['requires_approval' => true]);
        $reviewer = User::factory()->reviewer()->create();
        $bundle = $this->createBundle($uploader);

        $this->travelTo(now());
        $this->actingAsUser($uploader)
            ->postJson("/upload/{$bundle->slug}/complete", [
                'auth' => $bundle->owner_token,
            ], $this->uploadHeaders($bundle))
            ->assertOk();

        $request = ApprovalRequest::where('bundle_id', $bundle->id)->firstOrFail();

        $this->travel(2)->days();

        $this->actingAsUser($reviewer)
            ->postJson("/approval/{$request->id}/approve", [], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $bundle->refresh();
        $this->assertSame(
            now()->addSeconds(86400)->getTimestamp(),
            $bundle->expires_at->getTimestamp(),
        );
        $this->assertTrue($bundle->expires_at->isFuture());

        $this->get("/bundle/{$bundle->slug}/preview?auth={$bundle->preview_token}")
            ->assertOk()
            ->assertSee('Test bundle');
    }

    public function test_non_approval_user_gets_immediate_links(): void
    {
        Mail::fake();
        config(['approval.required_default' => false]);

        $user = User::factory()->create(['requires_approval' => false]);
        $bundle = $this->createBundle($user);

        $this->actingAsUser($user)
            ->postJson("/upload/{$bundle->slug}/complete", [
                'auth' => $bundle->owner_token,
            ], $this->uploadHeaders($bundle))
            ->assertOk()
            ->assertJsonPath('status', BundleStatus::Approved->value)
            ->assertJsonPath('completed', true);

        $bundle->refresh();
        $this->assertNotNull($bundle->preview_link);
        $this->assertNotNull($bundle->download_link);
        $this->assertDatabaseMissing('approval_requests', ['bundle_id' => $bundle->id]);

        Mail::assertNothingSent();
    }

    public function test_pending_bundle_preview_is_blocked(): void
    {
        config(['approval.required_default' => true]);

        $user = User::factory()->create();
        $bundle = $this->createBundle($user, BundleStatus::PendingApproval);

        $this->get("/bundle/{$bundle->slug}/preview?auth={$bundle->preview_token}")
            ->assertNotFound();
    }

    public function test_reviewer_can_approve_pending_request(): void
    {
        Mail::fake();

        $uploader = User::factory()->create(['requires_approval' => true]);
        $reviewer = User::factory()->reviewer()->create();
        $bundle = $this->createBundle($uploader, BundleStatus::PendingApproval, completed: true);
        $request = ApprovalRequest::create([
            'bundle_id' => $bundle->id,
            'requested_by' => $uploader->id,
            'status' => ApprovalRequestStatus::Pending,
        ]);

        $this->actingAsUser($reviewer)
            ->postJson("/approval/{$request->id}/approve", [], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $bundle->refresh();
        $request->refresh();

        $this->assertSame(BundleStatus::Approved, $bundle->status);
        $this->assertNotNull($bundle->preview_link);
        $this->assertSame(ApprovalRequestStatus::Approved, $request->status);
        $this->assertSame($reviewer->id, $request->reviewer_id);

        Mail::assertSent(BundleApprovedMail::class, fn ($mail) => $mail->hasTo($uploader->email));
    }

    public function test_reviewer_deny_requires_reason(): void
    {
        Mail::fake();

        $uploader = User::factory()->create(['requires_approval' => true]);
        $reviewer = User::factory()->reviewer()->create();
        $bundle = $this->createBundle($uploader, BundleStatus::PendingApproval, completed: true);
        $request = ApprovalRequest::create([
            'bundle_id' => $bundle->id,
            'requested_by' => $uploader->id,
            'status' => ApprovalRequestStatus::Pending,
        ]);

        $this->actingAsUser($reviewer)
            ->postJson("/approval/{$request->id}/deny", [], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertUnprocessable();

        $this->actingAsUser($reviewer)
            ->postJson("/approval/{$request->id}/deny", [
                'reason' => 'Contains confidential data',
            ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $bundle->refresh();
        $request->refresh();

        $this->assertSame(BundleStatus::Denied, $bundle->status);
        $this->assertFalse($bundle->completed);
        $this->assertNull($bundle->preview_link);
        $this->assertSame('Contains confidential data', $request->notes);

        Mail::assertSent(BundleDeniedMail::class, fn ($mail) => $mail->hasTo($uploader->email));
    }

    public function test_denied_bundle_can_be_resubmitted(): void
    {
        Mail::fake();
        config(['approval.required_default' => true]);

        $user = User::factory()->create(['requires_approval' => true]);
        $bundle = $this->createBundle($user, BundleStatus::Denied, completed: false);
        ApprovalRequest::create([
            'bundle_id' => $bundle->id,
            'requested_by' => $user->id,
            'status' => ApprovalRequestStatus::Denied,
            'notes' => 'Previous denial',
        ]);

        $this->actingAsUser($user)
            ->postJson("/upload/{$bundle->slug}/complete", [
                'auth' => $bundle->owner_token,
            ], $this->uploadHeaders($bundle))
            ->assertOk()
            ->assertJsonPath('status', BundleStatus::PendingApproval->value);

        $this->assertSame(2, ApprovalRequest::where('bundle_id', $bundle->id)->count());
    }

    public function test_standard_user_cannot_access_reviewer_queue(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->get('/approval')
            ->assertForbidden();
    }

    public function test_cannot_modify_pending_approval_bundle(): void
    {
        $user = User::factory()->create();
        $bundle = $this->createBundle($user, BundleStatus::PendingApproval, completed: true);

        $this->actingAsUser($user)
            ->postJson("/upload/{$bundle->slug}", [
                'title' => 'Changed',
                'expiry' => '86400',
                'auth' => $bundle->owner_token,
            ], $this->uploadHeaders($bundle))
            ->assertForbidden();
    }

    public function test_approved_bundle_allows_preview(): void
    {
        $user = User::factory()->create();
        $bundle = $this->createBundle($user, BundleStatus::Approved, completed: true);

        $this->get("/bundle/{$bundle->slug}/preview?auth={$bundle->preview_token}")
            ->assertOk()
            ->assertSee('Test bundle');
    }

    public function test_denied_bundle_preview_is_blocked(): void
    {
        $user = User::factory()->create();
        $bundle = $this->createBundle($user, BundleStatus::Denied, completed: false);

        $this->get("/bundle/{$bundle->slug}/preview?auth={$bundle->preview_token}")
            ->assertNotFound();
    }

    public function test_complete_rejects_bundle_without_files(): void
    {
        $user = User::factory()->create(['requires_approval' => false]);
        $slug = 'empty-'.Str::lower(Str::random(8));
        $bundle = Bundle::create([
            'user_id' => $user->id,
            'slug' => $slug,
            'title' => 'Empty bundle',
            'owner_token' => substr(sha1($slug.'owner'), 0, 15),
            'preview_token' => substr(sha1($slug.'preview'), 0, 15),
            'completed' => false,
            'status' => BundleStatus::Draft,
            'expiry' => '86400',
            'fullsize' => 0,
            'max_downloads' => 0,
            'downloads' => 0,
        ]);

        $this->actingAsUser($user)
            ->postJson("/upload/{$bundle->slug}/complete", [
                'auth' => $bundle->owner_token,
            ], $this->uploadHeaders($bundle))
            ->assertStatus(422)
            ->assertJsonPath('message', __('approval.bundle-has-no-files'));
    }

    public function test_all_reviewers_are_notified_on_submit(): void
    {
        Mail::fake();
        config(['approval.required_default' => true]);

        $user = User::factory()->create(['requires_approval' => null]);
        $reviewerA = User::factory()->reviewer()->create();
        $reviewerB = User::factory()->reviewer()->create();
        $bundle = $this->createBundle($user);

        $this->actingAsUser($user)
            ->postJson("/upload/{$bundle->slug}/complete", [
                'auth' => $bundle->owner_token,
            ], $this->uploadHeaders($bundle))
            ->assertOk();

        Mail::assertSent(ApprovalRequestSubmittedMail::class, 2);
        Mail::assertSent(ApprovalRequestSubmittedMail::class, fn ($mail) => $mail->hasTo($reviewerA->email));
        Mail::assertSent(ApprovalRequestSubmittedMail::class, fn ($mail) => $mail->hasTo($reviewerB->email));
    }

    public function test_user_override_false_skips_approval_when_group_requires_it(): void
    {
        Mail::fake();
        config(['approval.required_default' => true]);

        $user = User::factory()->create(['requires_approval' => false]);
        $group = Group::create([
            'name' => 'Approval Required',
            'slug' => 'approval-required-'.Str::lower(Str::random(4)),
            'requires_approval' => true,
        ]);
        $user->groups()->attach($group);
        $bundle = $this->createBundle($user);

        $this->actingAsUser($user)
            ->postJson("/upload/{$bundle->slug}/complete", [
                'auth' => $bundle->owner_token,
            ], $this->uploadHeaders($bundle))
            ->assertOk()
            ->assertJsonPath('status', BundleStatus::Approved->value);

        $bundle->refresh();
        $this->assertNotNull($bundle->preview_link);
        $this->assertDatabaseMissing('approval_requests', ['bundle_id' => $bundle->id]);
        Mail::assertNothingSent();
    }

    private function createBundle(
        User $user,
        BundleStatus $status = BundleStatus::Draft,
        bool $completed = false,
    ): Bundle {
        $slug = 'bundle-'.Str::lower(Str::random(8));

        $bundle = Bundle::create([
            'user_id' => $user->id,
            'slug' => $slug,
            'title' => 'Test bundle',
            'owner_token' => substr(sha1($slug.'owner'), 0, 15),
            'preview_token' => substr(sha1($slug.'preview'), 0, 15),
            'completed' => $completed,
            'status' => $status,
            'expiry' => '86400',
            'fullsize' => 100,
            'max_downloads' => 0,
            'downloads' => 0,
        ]);

        File::create([
            'uuid' => (string) Str::uuid(),
            'bundle_id' => $bundle->id,
            'original' => 'test.txt',
            'filesize' => 100,
            'fullpath' => "{$slug}/test.txt",
            'filename' => 'test.txt',
            'status' => true,
        ]);

        return $bundle;
    }

    private function uploadHeaders(Bundle $bundle): array
    {
        return [
            'X-Upload-Auth' => $bundle->owner_token,
            'X-Requested-With' => 'XMLHttpRequest',
        ];
    }
}
