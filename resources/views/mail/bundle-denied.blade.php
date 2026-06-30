{{ __('approval.mail.denied-body') }}

{{ __('approval.mail.bundle-title') }}: {{ $bundle->title ?? __('approval.untitled-bundle') }}

{{ __('approval.mail.reason') }}: {{ $reason }}

{{ __('approval.mail.denied-resubmit') }}

{{ __('approval.mail.denied-cta') }}: {!! route('upload.create.show', $bundle) !!}
