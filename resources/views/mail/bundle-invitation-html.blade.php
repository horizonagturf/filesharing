@extends('mail.layout')

@section('content')
    <p style="margin: 0 0 16px; font-size: 15px; color: #374151;">{{ __('invitation.mail.invitation-body') }}</p>

    @include('mail.partials.details', [
        'rows' => [
            __('invitation.invitation-bundle') => $recipient->bundle->title ?? __('invitation.untitled-bundle'),
        ],
    ])

    @include('mail.partials.button', [
        'url' => $invitationUrl,
        'label' => __('invitation.mail.invitation-cta'),
    ])

    <p style="margin: 0; font-size: 13px; color: #6b7280;">
        @if ($recipient->bundle->require_otp)
            {{ __('invitation.mail.invitation-link') }}
            {{ __('invitation.mail.invitation-otp-note') }}
        @else
            {{ __('invitation.mail.invitation-link-no-otp') }}
        @endif
    </p>
@endsection
