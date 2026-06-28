{{ __('invitation.mail.invitation-body') }}

{{ __('invitation.invitation-bundle') }}: {{ $recipient->bundle->title ?? __('invitation.untitled-bundle') }}

{{ __('invitation.mail.invitation-link') }}
{{ $invitationUrl }}
