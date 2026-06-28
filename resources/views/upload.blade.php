@extends('layout')

@section('page_title', __('app.upload-files-title'))

@push('vite')
    @vite('resources/js/upload.js')
@endpush

@push('scripts')
<script>
    window.__uploadConfig = {
        bundle: @js($bundle),
        canUseStaticLink: @js($canUseStaticLink),
        invitationMode: @js($invitationMode),
        maxFiles: @js(config('sharing.max_files')),
        maxFileSize: @js(Upload::fileMaxSize()),
        blockedExtensions: @js($blockedExtensions),
        fileTypeBlockedMessage: @js(__('app.file-type-blocked')),
        confirmCompleteApproval: @js(__('approval.confirm-complete-approval')),
        confirmCompleteDirect: @js(__('approval.confirm-complete-direct')),
        confirmDelete: @js(__('app.confirm-delete')),
        confirmDeleteBundle: @js(__('app.confirm-delete-bundle')),
        invitationResent: @js(__('invitation.invitation-resent')),
        dictMaxFilesExceeded: @js(__('app.files-count-limit')),
        dictFileTooBig: @js(__('app.file-too-big')),
        dictDefaultMessage: @js(__('app.dropzone-text')),
        dictResponseError: @js(__('app.server-answered')),
        steps: [
            { title: @js(__('app.upload-settings')) },
            { title: @js(__('app.upload-files-title')) },
            { title: @js(__('app.download-links')) },
        ],
    };
</script>
@endpush

@section('content')
    <div x-data="upload">
        @include('upload._modal')
        @include('upload._stepper')

        @include('upload.settings')
        @include('upload.files')
        @include('upload.complete')
    </div>
@endsection
