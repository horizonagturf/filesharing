<?php

namespace Tests\Feature;

use App\Enums\ApprovalRequestStatus;
use App\Enums\BundleStatus;
use App\Models\ApprovalRequest;
use App\Models\Bundle;
use App\Models\File;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApprovalQueueUiTest extends TestCase
{
    public function test_reviewer_sees_pending_requests_on_queue_page(): void
    {
        $reviewer = User::factory()->reviewer()->create();
        $uploader = User::factory()->create(['name' => 'Queue Uploader']);
        $bundle = $this->createBundle($uploader, 'Queue bundle title');

        ApprovalRequest::create([
            'bundle_id' => $bundle->id,
            'requested_by' => $uploader->id,
            'status' => ApprovalRequestStatus::Pending,
        ]);

        $this->actingAsUser($reviewer)
            ->get(route('approval.index'))
            ->assertOk()
            ->assertSee(__('approval.queue-title'))
            ->assertSee('Queue bundle title')
            ->assertSee('Queue Uploader');
    }

    public function test_reviewer_sees_empty_queue_message_when_none_pending(): void
    {
        $reviewer = User::factory()->reviewer()->create();

        $this->actingAsUser($reviewer)
            ->get(route('approval.index'))
            ->assertOk()
            ->assertSee(__('approval.queue-empty'));
    }

    public function test_reviewer_can_view_bundle_details_on_show_page(): void
    {
        $reviewer = User::factory()->reviewer()->create();
        $uploader = User::factory()->create();
        $bundle = $this->createBundle($uploader, 'Review me');
        $request = ApprovalRequest::create([
            'bundle_id' => $bundle->id,
            'requested_by' => $uploader->id,
            'status' => ApprovalRequestStatus::Pending,
        ]);

        $this->actingAsUser($reviewer)
            ->get(route('approval.show', $request))
            ->assertOk()
            ->assertSee('Review me')
            ->assertSee('test.txt')
            ->assertSee(__('approval.approve'))
            ->assertSee(__('approval.deny'));
    }

    public function test_show_page_not_found_for_non_pending_request(): void
    {
        $reviewer = User::factory()->reviewer()->create();
        $uploader = User::factory()->create();
        $bundle = $this->createBundle($uploader, 'Old request');
        $request = ApprovalRequest::create([
            'bundle_id' => $bundle->id,
            'requested_by' => $uploader->id,
            'status' => ApprovalRequestStatus::Approved,
            'reviewed_at' => now(),
        ]);

        $this->actingAsUser($reviewer)
            ->get(route('approval.show', $request))
            ->assertNotFound();
    }

    private function createBundle(User $user, string $title): Bundle
    {
        $slug = 'queue-'.Str::lower(Str::random(8));

        $bundle = Bundle::create([
            'user_id' => $user->id,
            'slug' => $slug,
            'title' => $title,
            'owner_token' => substr(sha1($slug.'owner'), 0, 15),
            'preview_token' => substr(sha1($slug.'preview'), 0, 15),
            'completed' => true,
            'status' => BundleStatus::PendingApproval,
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
}
