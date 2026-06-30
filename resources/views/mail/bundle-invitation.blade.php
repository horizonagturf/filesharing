{{ __('invitation.mail.invitation-body') }}

{{ __('invitation.invitation-bundle') }}: {{ $recipient->bundle->title ?? __('invitation.untitled-bundle') }}

@if ($recipient->bundle->require_otp)
{{ __('invitation.mail.invitation-link') }}
{{ __('invitation.mail.invitation-otp-note') }}
@else
{{ __('invitation.mail.invitation-link-no-otp') }}
@endif
{!! $invitationUrl !!}