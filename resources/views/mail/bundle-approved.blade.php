Your bundle has been approved and is ready to share.

Title: {{ $bundle->title ?? __('approval.untitled-bundle') }}

@if ($bundle->share_mode === \App\Enums\ShareMode::Invitation)
Invitations have been sent to your recipients.
@else
Preview: {{ $bundle->preview_link }}
Download: {{ $bundle->download_link }}
@endif
