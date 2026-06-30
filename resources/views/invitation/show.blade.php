@extends('layout')

@section('page_title', __('invitation.invitation-title'))

@section('content')
    <x-ui.page-header
        :title="__('invitation.invitation-title')"
        :subtitle="__('invitation.invitation-intro')"
    />

    <p class="mb-6 text-sm text-gray-500">
        <x-help-link topic="for-recipients" />
    </p>

    <x-ui.card class="mb-6">
        <p class="text-xs font-medium uppercase tracking-wide text-primary">@lang('invitation.invitation-bundle')</p>
        <p class="mt-1 font-medium text-gray-900">{{ $bundle->title ?? __('invitation.untitled-bundle') }}</p>
        <p class="mt-1 text-sm text-gray-500">{{ $recipient->email }}</p>
    </x-ui.card>

    @if (session('status'))
        <x-ui.alert variant="success" class="mb-4">{{ session('status') }}</x-ui.alert>
    @endif

    @if ($errors->any())
        <x-ui.alert variant="danger" class="mb-4">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </x-ui.alert>
    @endif

    <div class="space-y-6 max-w-md">
        <div>
            <p class="mb-2 text-sm font-medium text-gray-700">@lang('invitation.invitation-request-otp')</p>
            <form method="POST" action="{{ $otpRequestUrl }}">
                @csrf
                <x-ui.button type="submit" variant="primary" class="w-full">
                    @lang('invitation.invitation-request-otp')
                </x-ui.button>
            </form>
        </div>

        <div class="border-t border-gray-100 pt-6">
            <p class="mb-2 text-sm font-medium text-gray-700">@lang('invitation.invitation-verify-otp')</p>
            <form method="POST" action="{{ $otpVerifyUrl }}" class="space-y-4">
                @csrf
                <x-ui.input
                    id="code"
                    name="code"
                    type="text"
                    inputmode="numeric"
                    pattern="[0-9]{6}"
                    maxlength="6"
                    required
                    :label="__('invitation.invitation-code-label')"
                    :hint="__('invitation.invitation-code-help', ['email' => $recipient->email])"
                    class="text-center tracking-widest text-lg"
                />
                <x-ui.button type="submit" variant="primary" class="w-full">
                    @lang('invitation.invitation-verify-otp')
                </x-ui.button>
            </form>
        </div>
    </div>
@endsection
