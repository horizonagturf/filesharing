{{ __('approval.mail.approved-body') }}

{{ __('approval.mail.bundle-title') }}: {{ $bundle->title ?? __('approval.untitled-bundle') }}

@if ($bundle->share_mode === \App\Enums\ShareMode::Invitation)
{{ __('approval.mail.approved-invitations-sent') }}

{{ __('approval.mail.approved-view-bundle-cta') }}: {!! route('upload.create.show', $bundle) !!}
@else
{{ __('approval.mail.approved-preview') }}: {!! $bundle->preview_link !!}
{{ __('approval.mail.approved-download') }}: {!! $bundle->download_link !!}
@endif
