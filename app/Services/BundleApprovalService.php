<?php

namespace App\Services;

use App\Enums\ApprovalRequestStatus;
use App\Enums\AuditEvent;
use App\Enums\BundleStatus;
use App\Mail\ApprovalRequestSubmittedMail;
use App\Mail\BundleApprovedMail;
use App\Mail\BundleDeniedMail;
use App\Models\ApprovalRequest;
use App\Models\Bundle;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

class BundleApprovalService
{
    public function __construct(
        private readonly ApprovalPolicy $approvalPolicy,
        private readonly BundleInvitationService $invitationService,
    ) {}

    public function complete(Bundle $bundle, ?User $user): Bundle
    {
        if (! $bundle->isEditable()) {
            throw new InvalidArgumentException(__('approval.bundle-not-editable'));
        }

        if ($bundle->files()->where('status', true)->doesntExist()) {
            throw new InvalidArgumentException(__('approval.bundle-has-no-files'));
        }

        if ($this->invitationService->usesInvitationMode($bundle) && $bundle->recipients()->doesntExist()) {
            throw new InvalidArgumentException(__('invitation.recipients-required'));
        }

        return DB::transaction(function () use ($bundle, $user) {
            $this->finalizeMetadata($bundle);

            if ($user !== null && $this->approvalPolicy->requiresApproval($user)) {
                $bundle->status = BundleStatus::PendingApproval;
                $bundle->completed = true;
                $bundle->expires_at = null;
                $bundle->preview_link = null;
                $bundle->download_link = null;
                $bundle->save();

                $request = ApprovalRequest::create([
                    'bundle_id' => $bundle->id,
                    'requested_by' => $user->id,
                    'status' => ApprovalRequestStatus::Pending,
                ]);

                $this->notifyReviewers($request);

                Audit::log(AuditEvent::BundleSubmittedForApproval, [
                    'bundle' => $bundle,
                    'user' => $user,
                    'metadata' => ['approval_request_id' => $request->id],
                ]);

                return $bundle->fresh(['recipients']);
            }

            $this->approveDirectly($bundle);

            return $bundle->fresh(['recipients']);
        });
    }

    public function approve(ApprovalRequest $request, User $reviewer): Bundle
    {
        if (! $request->isPending()) {
            throw new InvalidArgumentException(__('approval.request-not-pending'));
        }

        return DB::transaction(function () use ($request, $reviewer) {
            $request->update([
                'status' => ApprovalRequestStatus::Approved,
                'reviewer_id' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            $bundle = $request->bundle;
            $this->setExpiresAt($bundle);
            $this->publishBundle($bundle);
            $bundle->completed = true;
            $bundle->save();

            if ($bundle->user?->email) {
                Mail::to($bundle->user->email)->queue(new BundleApprovedMail($bundle));
            }

            Audit::log(AuditEvent::BundleApproved, [
                'bundle' => $bundle,
                'user' => $reviewer,
                'metadata' => [
                    'approval_request_id' => $request->id,
                    'reviewer_id' => $reviewer->id,
                ],
            ]);

            return $bundle->fresh(['recipients']);
        });
    }

    public function deny(ApprovalRequest $request, User $reviewer, string $reason): Bundle
    {
        if (! $request->isPending()) {
            throw new InvalidArgumentException(__('approval.request-not-pending'));
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException(__('approval.deny-reason-required'));
        }

        return DB::transaction(function () use ($request, $reviewer, $reason) {
            $request->update([
                'status' => ApprovalRequestStatus::Denied,
                'reviewer_id' => $reviewer->id,
                'notes' => $reason,
                'reviewed_at' => now(),
            ]);

            $bundle = $request->bundle;
            $bundle->status = BundleStatus::Denied;
            $bundle->completed = false;
            $bundle->preview_link = null;
            $bundle->download_link = null;
            $bundle->save();

            if ($bundle->user?->email) {
                Mail::to($bundle->user->email)->queue(new BundleDeniedMail($bundle, $reason));
            }

            Audit::log(AuditEvent::BundleDenied, [
                'bundle' => $bundle,
                'user' => $reviewer,
                'metadata' => [
                    'approval_request_id' => $request->id,
                    'reviewer_id' => $reviewer->id,
                    'reason' => $reason,
                ],
            ]);

            return $bundle->fresh(['recipients']);
        });
    }

    public function assertEditable(Bundle $bundle): void
    {
        if (! $bundle->isEditable()) {
            abort(403, __('approval.bundle-not-editable'));
        }
    }

    private function approveDirectly(Bundle $bundle): void
    {
        $this->setExpiresAt($bundle);
        $this->publishBundle($bundle);
        $bundle->completed = true;
        $bundle->save();

        Audit::log(AuditEvent::BundleApproved, [
            'bundle' => $bundle,
            'user' => $bundle->user,
            'metadata' => ['auto_approved' => true],
        ]);
    }

    private function finalizeMetadata(Bundle $bundle): void
    {
        $bundle->fullsize = $bundle->files->sum('filesize');
        $bundle->deletion_link = route('upload.bundle.delete', ['bundle' => $bundle]);
    }

    private function setExpiresAt(Bundle $bundle): void
    {
        if ($bundle->expiry === 'forever') {
            $bundle->expires_at = null;
        } else {
            $bundle->expires_at = now()->addSeconds((int) $bundle->expiry);
        }
    }

    private function generateLinks(Bundle $bundle): void
    {
        $bundle->preview_link = route('bundle.preview', ['bundle' => $bundle, 'auth' => $bundle->preview_token]);
        $bundle->download_link = route('bundle.zip.download', ['bundle' => $bundle, 'auth' => $bundle->preview_token]);
    }

    private function publishBundle(Bundle $bundle): void
    {
        if ($this->invitationService->usesInvitationMode($bundle)) {
            if ($bundle->recipients()->doesntExist()) {
                throw new InvalidArgumentException(__('invitation.recipients-required'));
            }

            $bundle->status = BundleStatus::Approved;
            $bundle->preview_link = null;
            $bundle->download_link = null;
            $bundle->save();

            $this->invitationService->sendInvitations($bundle);

            return;
        }

        $this->generateLinks($bundle);
        $bundle->status = BundleStatus::Approved;
    }

    private function notifyReviewers(ApprovalRequest $request): void
    {
        ReviewerPool::all()
            ->filter(fn (User $reviewer) => filled($reviewer->email))
            ->each(fn (User $reviewer) => Mail::to($reviewer->email)->queue(
                new ApprovalRequestSubmittedMail($request)
            ));
    }
}
