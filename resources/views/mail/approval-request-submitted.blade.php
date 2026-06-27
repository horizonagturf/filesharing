A new bundle requires your approval.

Title: {{ $approvalRequest->bundle->title ?? __('approval.untitled-bundle') }}
Uploader: {{ $approvalRequest->requester->name ?? $approvalRequest->requester->username }}
Files: {{ $approvalRequest->bundle->files->count() }}
Size: {{ number_format($approvalRequest->bundle->fullsize / 1000000, 1) }} MB

Review: {{ route('approval.show', $approvalRequest) }}
