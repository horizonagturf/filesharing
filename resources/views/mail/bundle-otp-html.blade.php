@extends('mail.layout')

@section('content')
    <p style="margin: 0 0 8px; font-size: 15px; color: #374151;">{{ __('invitation.mail.otp-body') }}</p>

    @include('mail.partials.code', ['code' => $code])

    <p style="margin: 0 0 20px; font-size: 13px; color: #6b7280;">
        {{ __('invitation.mail.otp-expires', ['minutes' => config('invitation.otp_expiry_minutes', 15)]) }}
    </p>

    @include('mail.partials.details', [
        'rows' => [
            __('invitation.invitation-bundle') => $recipient->bundle->title ?? __('invitation.untitled-bundle'),
        ],
    ])
@endsection
