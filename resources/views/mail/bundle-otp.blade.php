{{ __('invitation.mail.otp-body') }}

{{ $code }}

{{ __('invitation.mail.otp-expires', ['minutes' => config('invitation.otp_expiry_minutes', 15)]) }}

{{ __('invitation.invitation-bundle') }}: {{ $recipient->bundle->title ?? __('invitation.untitled-bundle') }}
