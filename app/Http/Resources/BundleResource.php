<?php

namespace App\Http\Resources;

use App\Helpers\Upload;
use App\Services\ApprovalPolicy;
use App\Services\BundleInvitationService;
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
        $full = false;
        if (Auth::check() || Upload::canUpload($request->ip())) {
            $full = true;
        }

        $invitationMode = app(BundleInvitationService::class)->usesInvitationMode($this->resource);
        $previewLink = $this->preview_link;
        $downloadLink = $this->download_link;

        if ($invitationMode) {
            if ($full) {
                $previewLink = null;
                $downloadLink = null;
            } elseif ($this->isShareable()) {
                $previewLink = route('bundle.preview', ['bundle' => $this->resource]);
                $downloadLink = route('bundle.zip.download', ['bundle' => $this->resource]);
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
            'files' => FileResource::collection($this->files),
            'preview_link' => $previewLink,
            'download_link' => $downloadLink,
            'invitation_mode' => $invitationMode,
            'share_mode' => $this->share_mode?->value,
            'can_use_static_link' => $this->when($full === true && Auth::check(), fn () => app(ShareModePolicy::class)->canUseStaticLinks(Auth::user())),
            'recipients' => $this->when($full === true, fn () => $this->recipients->map(fn ($recipient) => [
                'id' => $recipient->id,
                'email' => $recipient->email,
                'invited_at' => $recipient->invited_at,
                'verified_at' => $recipient->verified_at,
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

        return $response;
    }
}
