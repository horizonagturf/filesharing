{{ __('approval.mail.reviewer-body') }}

{{ __('approval.mail.bundle-title') }}: {{ $approvalRequest->bundle->title ?? __('approval.untitled-bundle') }}
{{ __('approval.mail.uploader') }}: {{ $approvalRequest->requester->name ?? $approvalRequest->requester->username }}
{{ __('approval.mail.files') }}: {{ $approvalRequest->bundle->files->count() }}
{{ __('approval.mail.size') }}: {{ number_format($approvalRequest->bundle->fullsize / 1000000, 1) }} MB

{{ __('approval.mail.reviewer-cta') }}: {!! route('approval.show', $approvalRequest) !!}
