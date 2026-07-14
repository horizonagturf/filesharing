<?php

namespace Tests\Feature;

use App\Enums\ApprovalRequestStatus;
use App\Enums\AuditEvent;
use App\Enums\BundleStatus;
use App\Enums\ShareMode;
use App\Models\ApprovalRequest;
use App\Models\Bundle;
use App\Models\BundleRecipient;
use App\Models\File;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApprovalReviewDownloadTest extends TestCase
{
    /** @var list<string> */
    private array $slugs = [];

    protected function tearDown(): void
    {
        foreach ($this->slugs as $slug) {
            Storage::disk('uploads')->deleteDirectory($slug);
        }

        parent::tearDown();
    }

    public function test_show_page_lists_recipients(): void
    {
        $reviewer = User::factory()->reviewer()->create();
        $uploader = User::factory()->create();
        [$bundle, $file, $request] = $this->createPendingReview($uploader);

        BundleRecipient::create([
            'bundle_id' => $bundle->id,
            'email' => 'alice@example.com',
            'invited_at' => now(),
        ]);

        $this->actingAsUser($reviewer)
            ->get(route('approval.show', $request))
            ->assertOk()
            ->assertSee('alice@example.com')
            ->assertSee(__('invitation.recipients'))
            ->assertSee(__('app.download'));
    }

    public function test_reviewer_can_download_pending_file_without_incrementing_downloads(): void
    {
        $reviewer = User::factory()->reviewer()->create();
        $uploader = User::factory()->create();
        [$bundle, $file, $request, $contents] = $this->createPendingReview($uploader);

        $response = $this->actingAsUser($reviewer)
            ->get(route('approval.file.download', [
                'approvalRequest' => $request,
                'file' => $file,
            ]));

        $response->assertOk();
        $this->assertSame($contents, $response->streamedContent());
        $this->assertSame(0, $bundle->fresh()->downloads);
        $this->assertDatabaseHas('audit_logs', [
            'event_type' => AuditEvent::FileDownloaded->value,
            'bundle_id' => $bundle->id,
            'file_id' => $file->id,
            'actor_id' => $reviewer->id,
        ]);
    }

    public function test_non_reviewer_cannot_download_approval_file(): void
    {
        $uploader = User::factory()->create();
        $other = User::factory()->create();
        [$bundle, $file, $request] = $this->createPendingReview($uploader);

        $this->actingAsUser($other)
            ->get(route('approval.file.download', [
                'approvalRequest' => $request,
                'file' => $file,
            ]))
            ->assertForbidden();
    }

    public function test_reviewer_cannot_download_file_from_another_bundle(): void
    {
        $reviewer = User::factory()->reviewer()->create();
        $uploader = User::factory()->create();
        [$bundle, $file, $request] = $this->createPendingReview($uploader, 'pending-a');

        $otherSlug = 'pending-b-'.Str::random(4);
        $this->slugs[] = $otherSlug;

        $otherBundle = Bundle::create([
            'user_id' => $uploader->id,
            'slug' => $otherSlug,
            'title' => 'Other',
            'owner_token' => substr(sha1($otherSlug.'owner'), 0, 15),
            'preview_token' => substr(sha1($otherSlug.'preview'), 0, 15),
            'share_mode' => ShareMode::Invitation,
            'completed' => true,
            'status' => BundleStatus::PendingApproval,
            'expiry' => '86400',
            'fullsize' => 0,
            'max_downloads' => 0,
            'downloads' => 0,
        ]);

        $otherFile = File::create([
            'uuid' => (string) Str::uuid(),
            'bundle_id' => $otherBundle->id,
            'original' => 'other.txt',
            'filesize' => 5,
            'fullpath' => "{$otherSlug}/other",
            'filename' => 'other',
            'status' => true,
        ]);
        Storage::disk('uploads')->put($otherFile->fullpath, 'other');

        $this->actingAsUser($reviewer)
            ->get(route('approval.file.download', [
                'approvalRequest' => $request,
                'file' => $otherFile,
            ]))
            ->assertNotFound();
    }

    /**
     * @return array{0: Bundle, 1: File, 2: ApprovalRequest, 3: string}
     */
    private function createPendingReview(User $uploader, string $prefix = 'pending-dl'): array
    {
        $slug = $prefix.'-'.Str::lower(Str::random(6));
        $this->slugs[] = $slug;

        $contents = 'review-content-'.$slug;
        $filename = (string) Str::uuid();
        $fullpath = "{$slug}/{$filename}";
        Storage::disk('uploads')->put($fullpath, $contents);

        $bundle = Bundle::create([
            'user_id' => $uploader->id,
            'slug' => $slug,
            'title' => 'Pending review',
            'owner_token' => substr(sha1($slug.'owner'), 0, 15),
            'preview_token' => substr(sha1($slug.'preview'), 0, 15),
            'share_mode' => ShareMode::Invitation,
            'completed' => true,
            'status' => BundleStatus::PendingApproval,
            'expiry' => '86400',
            'fullsize' => strlen($contents),
            'max_downloads' => 0,
            'downloads' => 0,
        ]);

        $file = File::create([
            'uuid' => (string) Str::uuid(),
            'bundle_id' => $bundle->id,
            'original' => 'report.txt',
            'filesize' => strlen($contents),
            'fullpath' => $fullpath,
            'filename' => $filename,
            'status' => true,
        ]);

        $request = ApprovalRequest::create([
            'bundle_id' => $bundle->id,
            'requested_by' => $uploader->id,
            'status' => ApprovalRequestStatus::Pending,
        ]);

        return [$bundle, $file, $request, $contents];
    }
}
