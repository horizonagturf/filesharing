@extends('mail.layout')

@section('content')
    <p style="margin: 0 0 16px; font-size: 15px; color: #374151;">{{ __('approval.mail.approved-body') }}</p>

    @include('mail.partials.details', [
        'rows' => [
            __('approval.mail.bundle-title') => $bundle->title ?? __('approval.untitled-bundle'),
        ],
    ])

    @if ($bundle->share_mode === \App\Enums\ShareMode::Invitation)
        <p style="margin: 0; font-size: 14px; color: #374151;">{{ __('approval.mail.approved-invitations-sent') }}</p>
    @else
        @include('mail.partials.button', [
            'url' => $bundle->preview_link,
            'label' => __('approval.mail.approved-preview-cta'),
        ])

        @include('mail.partials.button', [
            'url' => $bundle->download_link,
            'label' => __('approval.mail.approved-download-cta'),
        ])
    @endif
@endsection
