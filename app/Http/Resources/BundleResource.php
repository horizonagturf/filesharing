<?php

namespace App\Http\Resources;

use App\Services\ApprovalPolicy;
use App\Services\BundleInvitationService;
use App\Services\BundleOwnerAccess;
use App\Services\BundlePasswordAccess;
use App\Services\OtpPolicy;
use App\Services\ShareModePolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class BundleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /**
        Do not return private data on the preview page
         */
        $guestPreview = (bool) $request->attributes->get('bundle_guest_preview');

        $full = ! $guestPreview && BundleOwnerAccess::isOwner($request, $this->resource);

        $fileContext = $full
            ? FileResource::CONTEXT_OWNER
            : FileResource::CONTEXT_GUEST;

        $invitationService = app(BundleInvitationService::class);
        $invitationMode = $invitationService->usesInvitationMode($this->resource);
        $manualShareLinks = $invitationService->usesManualShareLinks($this->resource);
        $previewLink = $this->preview_link;
        $downloadLink = $this->download_link;

        if ($invitationMode && ! $manualShareLinks && $this->isShareable()) {
            $guestPreviewLink = route('bundle.preview', ['bundle' => $this->resource]);
            $guestDownloadLink = route('bundle.zip.download', ['bundle' => $this->resource]);

            if ($guestPreview || ! $full) {
                $previewLink = $guestPreviewLink;
                $downloadLink = $guestDownloadLink;
            } else {
                $previewLink = null;
                $downloadLink = null;
            }
        }

        $response = [
            'created_at' => $this->created_at,
            'completed' => (bool) $this->completed,
            'status' => $this->status?->value,
            'status_label' => $this->status ? __('approval.status-'.$this->status->value) : null,
            'expiry' => (int) $this->expiry,
            'expires_at' => $this->expires_at,
            'slug' => $this->slug,
            'fullsize' => (int) $this->fullsize,
            'title' => $this->title,
            'description' => $this->description,
            'description_html' => ! empty($this->description) ? Str::markdown($this->description) : null,
            'max_downloads' => (int) $this->max_downloads,
            'downloads' => (int) $this->downloads,
            'files' => $this->files->map(function ($file) use ($fileContext) {
                $file->setRelation('bundle', $this->resource);

                return (new FileResource($file))->context($fileContext);
            })->values(),
            'preview_link' => $previewLink,
            'download_link' => $downloadLink,
            'invitation_mode' => $invitationMode,
            'share_mode' => $this->share_mode?->value,
            'require_otp' => $this->when($full === true, (bool) $this->require_otp),
            'can_choose_otp' => $this->when($full === true && Auth::check(), fn () => app(OtpPolicy::class)->canChooseOtpSetting(Auth::user())),
            'can_use_static_link' => $this->when($full === true && Auth::check(), fn () => app(ShareModePolicy::class)->canUseStaticLinks(Auth::user())),
            'recipients' => $this->when($full === true, fn () => $this->recipients->map(fn ($recipient) => [
                'id' => $recipient->id,
                'email' => $recipient->email,
                'invited_at' => $recipient->invited_at,
                'verified_at' => $recipient->verified_at,
                'revoked_at' => $recipient->revoked_at,
            ])),
            'password' => $this->when($full === true, $this->password),
            'owner_token' => $this->when($full === true, $this->owner_token),
            'preview_token' => $this->when($full === true, $this->preview_token),
            'deletion_link' => $this->when($full === true, $this->deletion_link),
            'requires_approval' => $this->when($full === true && Auth::check(), fn () => app(ApprovalPolicy::class)->requiresApproval(Auth::user())),
            'denial_reason' => $this->when(
                $full === true && $this->status?->value === 'denied',
                fn () => $this->approvalRequests()
                    ->where('status', 'denied')
                    ->latest()
                    ->value('notes'),
            ),
            'is_editable' => $this->when($full === true, $this->isEditable()),
            'user' => $this->when($full === true, new UserResource($this->user)),
        ];

        if ($guestPreview || ! $full) {
            $unlockParams = ['bundle' => $this->resource];
            if ($request->query('auth')) {
                $unlockParams['auth'] = $request->query('auth');
            }

            $response['password_required'] = BundlePasswordAccess::requiresPassword($this->resource);
            $response['password_unlocked'] = BundlePasswordAccess::isUnlocked($this->resource);
            $response['unlock_url'] = route('bundle.unlock', $unlockParams);
        }

        return $response;
    }
}
