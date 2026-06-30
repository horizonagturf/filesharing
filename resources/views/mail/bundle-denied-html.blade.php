@extends('mail.layout')

@section('content')
    <p style="margin: 0 0 16px; font-size: 15px; color: #374151;">{{ __('approval.mail.denied-body') }}</p>

    @include('mail.partials.details', [
        'rows' => [
            __('approval.mail.bundle-title') => $bundle->title ?? __('approval.untitled-bundle'),
        ],
    ])

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 20px;">
        <tr>
            <td style="padding: 16px; background-color: #fef2f2; border: 1px solid #fecaca; border-radius: 8px;">
                <p style="margin: 0 0 4px; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #991b1b;">{{ __('approval.mail.reason') }}</p>
                <p style="margin: 0; font-size: 14px; color: #7f1d1d;">{{ $reason }}</p>
            </td>
        </tr>
    </table>

    <p style="margin: 0 0 20px; font-size: 14px; color: #374151;">{{ __('approval.mail.denied-resubmit') }}</p>

    @include('mail.partials.button', [
        'url' => route('upload.create.show', $bundle),
        'label' => __('approval.mail.denied-cta'),
    ])
@endsection
