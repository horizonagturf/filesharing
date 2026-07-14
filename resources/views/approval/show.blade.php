@extends('layout')

@section('page_title', __('approval.review-bundle'))

@push('scripts')
<script>
    window.__confirmApprove = @js(__('approval.confirm-approve'));
    window.__confirmDeny = @js(__('approval.confirm-deny'));
    window.__denyReasonRequired = @js(__('approval.deny-reason-required'));
    window.__unexpectedError = @js(__('app.unexpected-error'));
    window.__approveUrl = @js(route('approval.approve', $approvalRequest));
    window.__denyUrl = @js(route('approval.deny', $approvalRequest));
    window.__approvalIndexUrl = @js(route('approval.index'));
</script>
@endpush

@section('content')
    <div x-data="review">
        <template x-if="modal.show">
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true">
                <div class="fixed inset-0 bg-gray-500/75" x-on:click="modal.show = false"></div>
                <div class="relative w-full max-w-md rounded-xl bg-white p-6 shadow-xl ring-1 ring-gray-950/5">
                    <h3 class="text-base font-semibold text-gray-900">@lang('app.confirmation')</h3>
                    <p class="mt-2 text-sm text-gray-600" x-text="modal.text"></p>
                    <div class="mt-6 flex justify-end gap-3">
                        <x-ui.button variant="secondary" size="sm" x-on:click="modal.show = false">@lang('app.cancel')</x-ui.button>
                        <x-ui.button variant="primary" size="sm" x-on:click="confirmModal()" ::disabled="loading">@lang('app.confirm')</x-ui.button>
                    </div>
                </div>
            </div>
        </template>

        <x-ui.page-header
            :title="$approvalRequest->bundle->title ?? __('approval.untitled-bundle')"
            :subtitle="__('approval.submitted-by').': '.($approvalRequest->requester->name ?? $approvalRequest->requester->username).' · '.__('approval.submitted-at').': '.$approvalRequest->created_at->format('Y-m-d H:i')"
        />

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                @if ($approvalRequest->bundle->description)
                    <div class="prose prose-sm max-w-none text-gray-700">
                        {!! \Illuminate\Support\Str::markdown($approvalRequest->bundle->description) !!}
                    </div>
                @endif

                <div>
                    <h3 class="mb-2 text-sm font-semibold text-gray-900">@lang('app.files-list')</h3>
                    <ul class="divide-y divide-gray-100 rounded-lg ring-1 ring-gray-950/5">
                        @foreach ($approvalRequest->bundle->files as $file)
                            <li class="flex items-center justify-between gap-3 px-4 py-2 text-sm">
                                <div class="flex min-w-0 items-center gap-3">
                                    @if (\App\Helpers\Upload::isImageFilename((string) $file->original) && $file->thumbnail_path)
                                        <img
                                            src="{{ route('approval.file.thumbnail', ['approvalRequest' => $approvalRequest, 'file' => $file]) }}"
                                            alt="{{ $file->original }}"
                                            class="h-12 w-12 shrink-0 rounded object-cover ring-1 ring-gray-200"
                                        />
                                    @endif
                                    <span class="truncate text-gray-900">{{ $file->original }}</span>
                                </div>
                                <div class="ml-4 flex shrink-0 items-center gap-3">
                                    <span class="text-gray-500">{{ number_format($file->filesize / 1000000, 1) }} MB</span>
                                    <a
                                        href="{{ route('approval.file.download', ['approvalRequest' => $approvalRequest, 'file' => $file]) }}"
                                        class="font-medium text-primary hover:underline"
                                    >
                                        @lang('app.download')
                                    </a>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>

                @if ($approvalRequest->bundle->recipients->isNotEmpty())
                    <div>
                        <h3 class="mb-2 text-sm font-semibold text-gray-900">@lang('invitation.recipients')</h3>
                        <ul class="divide-y divide-gray-100 rounded-lg ring-1 ring-gray-950/5">
                            @foreach ($approvalRequest->bundle->recipients as $recipient)
                                <li class="flex items-center justify-between gap-3 px-4 py-2 text-sm">
                                    <span class="truncate text-gray-900">{{ $recipient->email }}</span>
                                    <span class="shrink-0 text-gray-500">
                                        @if ($recipient->isRevoked())
                                            @lang('invitation.recipient-revoked')
                                        @elseif ($recipient->isVerified())
                                            @lang('invitation.recipient-verified')
                                        @else
                                            @lang('invitation.recipient-pending')
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            <div class="space-y-4">
                <template x-if="error">
                    <x-ui.alert variant="danger" x-text="error"></x-ui.alert>
                </template>

                <div class="rounded-lg bg-gray-50 p-4 ring-1 ring-gray-950/5">
                    <div class="flex flex-col gap-2">
                        <x-ui.button variant="success" x-on:click="approve()" ::disabled="loading" class="w-full justify-center">
                            @lang('approval.approve')
                        </x-ui.button>
                        <x-ui.button variant="danger" x-on:click="denyForm = !denyForm" class="w-full justify-center">
                            @lang('approval.deny')
                        </x-ui.button>
                        <x-ui.button variant="ghost" href="{{ route('approval.index') }}" class="w-full justify-center" icon="arrow-left" icon-position="left">
                            @lang('app.back')
                        </x-ui.button>
                    </div>
                </div>

                <div x-show="denyForm" x-cloak class="space-y-3">
                    <x-ui.textarea
                        id="deny-reason"
                        rows="3"
                        :label="__('approval.deny-reason')"
                        required
                        x-model="reason"
                        :placeholder="__('approval.deny-reason-placeholder')"
                    />
                    <x-ui.button variant="danger" x-on:click="submitDeny()" ::disabled="loading" class="w-full justify-center">
                        @lang('approval.deny')
                    </x-ui.button>
                </div>
            </div>
        </div>
    </div>
@endsection
