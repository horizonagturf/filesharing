@extends('mail.layout')

@section('content')
    <p style="margin: 0 0 16px; font-size: 15px; color: #374151;">{{ __('approval.mail.reviewer-body') }}</p>

    @include('mail.partials.details', [
        'rows' => [
            __('approval.mail.bundle-title') => $approvalRequest->bundle->title ?? __('approval.untitled-bundle'),
            __('approval.mail.uploader') => $approvalRequest->requester->name ?? $approvalRequest->requester->username,
            __('approval.mail.files') => $approvalRequest->bundle->files->count(),
            __('approval.mail.size') => number_format($approvalRequest->bundle->fullsize / 1000000, 1).' MB',
        ],
    ])

    @include('mail.partials.button', [
        'url' => route('approval.show', $approvalRequest),
        'label' => __('approval.mail.reviewer-cta'),
    ])
@endsection
